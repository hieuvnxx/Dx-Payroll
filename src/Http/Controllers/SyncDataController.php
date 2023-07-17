<?php

namespace Dx\Payroll\Http\Controllers;

use App\Constants\BaseConstant;
use Carbon\Carbon;
use Dx\Payroll\Models\ZohoSection;
use Dx\Payroll\Models\ZohoRecord;
use Dx\Payroll\Models\ZohoRecordValue;
use Dx\Payroll\Models\ZohoForm;
use Dx\Payroll\Repositories\Eloquent\SectionsRepository;
use Dx\Payroll\Repositories\RecordsInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Illuminate\Http\Request;
use Dx\Payroll\Models\FactorMasterData;
use Dx\Payroll\Models\Fomular;
use Dx\Payroll\Models\FormMasterData;
use Dx\Payroll\Models\BonusSettings;
use Dx\Payroll\Models\GeneralSettings;
use Dx\Payroll\Models\LevelSettings;
use Dx\Payroll\Models\OverTime;
use Dx\Payroll\Models\OverTimeSettings;
use Dx\Payroll\Models\TaxSettings;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Dx\Payroll\Http\Controllers\ZohoController;
use Illuminate\Support\Facades\Log;


class SyncDataController
{

    public function __construct()
    {
        $this->payrollForm = [
            "form_master_form_name" => "form_master_data",
            "factor_master_form_name" => "factor_master_data",
            "fomular_form_name" => "fomular",
        ];
    }

    public function processSyncData(Request $arrInput){
        $config = app(RedisConfigFormInterface::class)->getConfig();
        if (empty($config))
        {
            return $this->sendError("No have Config");
        }
        $zohoForm = app(ZohoFormInterface::class);
        if(empty($arrInput->form_name))
        {
            $allForm = $zohoForm->all();
        }else{
            $allForm = $zohoForm->findByField('form_link_name', $arrInput->form_name);
        }
        if(empty($allForm))
        {
            return $this->sendError("Empty Form");
        };
        $zoho = app(ZohoController::class);

        foreach ($allForm as $form){
            $i = 0;
            while (true) {
                if($arrInput->zoho_id == 'all')
                {
                    $body['sIndex'] = $i * 200;
                    $body['limit'] = 200;
                    $resData = $zoho->getRecords($config[$form->form_link_name]['getRecords'], $body, true);
                }elseif (!empty($arrInput->zoho_id))
                {
                    //Delete record;
                    if (isset($arrInput->delete))
                    {
                        return $this->records->deleteRecords($form->id, $arrInput->zoho_id);
                    }

                    $resEmp = $zoho->getRecordByID($arrInput->zoho_id, $config[$form->form_link_name]['getRecordByID']);
                    if(array_key_exists('errors', $resEmp)){
                        $resData = $resEmp;
                    }else{
                        $resEmp['Zoho_ID'] = $arrInput->zoho_id;
                        $resData[] = $resEmp;
                    }
                }else
                {
                    $body['sIndex'] = $i * 200;
                    $body['limit'] = 200;
                    $body['searchParams'] = "{searchField: 'ModifiedTime', searchOperator: 'Yesterday'}";
                    $resData = $zoho->getRecords($config[$form->form_link_name]['getRecords'], $body, true);
                }
                if(empty($resData) || array_key_exists('errors', $resData) || (!empty($arrInput->zoho_id) && $i == 1)) break;
                if(!empty($resData)){
                    foreach ($resData as $data){
                        $idRecord = ZohoRecord::updateOrCreate(['form_id' => $form->id, 'zoho_id' => (string)$data['Zoho_ID']]);
                        foreach ($data as $key => $value){
                            if($key != "tabularSections"){
                                foreach ($form->attribute as $attribute){
                                    if($key == $attribute->field_label){
                                        if($attribute->type == "Lookup"){
                                            $valueIs = $data[$key.'.ID'] ?? $data[$key.'.id'] ?? $data[$key];
                                        }else{
                                            $valueIs = $value;
                                        }
                                        try{
                                            ZohoRecordValue::updateOrCreate(
                                                [
                                                    'record_id' => $idRecord->id,
                                                    'field_id' => $attribute->id,
                                                    'row_id' => '0'
                                                ],[
                                                    'value' => $valueIs,
                                                ]
                                            );
                                        } catch (\Exception $e) {
                                            Log::channel('dx')->info('Lỗi đồng bộ form '.$arrInput->form_name.' '.$e->getMessage().PHP_EOL);
                                        }
                                    }
                                }
                            }
                            else{
                                $arrAttributesForm = [];
                                foreach ($form->attribute as $attribute){
                                    if($attribute->section_id != 0){
                                        $arrAttributesForm[$attribute->section_id][] = $attribute->field_label;
                                        sort($arrAttributesForm[$attribute->section_id]);
                                    }
                                }
                                foreach ($data['tabularSections'] as $tabularName => $tabularValues) {
                                    if (!empty($tabularValues[0])) {
                                        // Sync Section Labels
                                        $arrAttributes = [];
                                        foreach ($tabularValues[0] as $attributes => $val){
                                            if (!str_contains(strtolower($attributes), ".id") && !str_contains(strtolower($attributes), ".displayvalue") && $attributes !== 'tabular.ROWID') {
                                                $arrAttributes[] = $attributes;
                                            }
                                            sort($arrAttributes);
                                        }
                                        try {
                                            foreach ($arrAttributesForm as $ky => $arrAttribute){
                                                if($arrAttribute == $arrAttributes){
                                                    $sectionID = ZohoSection::updateOrCreate(
                                                        [
                                                            'id' => $ky,
                                                            'form_id' => $form->id
                                                        ],[
                                                            'section_name' => $tabularName,
                                                        ]
                                                    );
                                                }
                                            }
                                            // Sync data in tabular
                                            foreach ($tabularValues as $tabularValue) {
                                                foreach ($tabularValue as $keys => $vals) {
                                                    foreach ($form->attribute as $attribute){
                                                        if($keys == $attribute->field_label){
                                                            ZohoRecordValue::updateOrCreate(
                                                                [
                                                                    'record_id' => $idRecord->id,
                                                                    'field_id' => $attribute->id,
                                                                    'row_id' => $tabularValue['tabular.ROWID']
                                                                ],[
                                                                    'value' => $vals,
                                                                ]
                                                            );
                                                        }
                                                    }
                                                }
                                            }
                                        }catch (\Exception $e){
                                            Log::channel('dx')->info('Lỗi đồng bộ form '.$arrInput->form_name.' '.$e->getMessage().PHP_EOL);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $i++;
                }
            }
        }
        echo 'End Sync '.$arrInput->form_name."\n";
    }

    public function syncFormData($data){
        $body['recordId'] = $data['Zoho_ID'];
        $responseData = $this->callZoho('forms/leave/getRecordByID', $body, true);
        foreach ($responseData['DayDetails'] as $date => $detail) {
            if (empty($detail)) {
                continue;
            }
            if (isset($data['action']) && $data['action'] == 'delete') {
                Leave::where('zoho_id', (string)$data["Zoho_ID"])->delete();
                continue;
            }
            try {
                Leave::updateOrCreate(
                    [
                        'zoho_id' => (string)$data['Zoho_ID'],
                        'date_leave' => date('Y-m-d', strtotime($date)),
                    ],
                    [
                        'employee' => $data['Employee_ID'],
                        'employee_id' => $data['Employee_ID.ID'],
                        'leave_type' => $data['Leavetype'],
                        'from' => $data['From'] != '' ? date('Y-m-d', strtotime($data["From"])) : null,
                        'to' => $data['To'] != '' ? date('Y-m-d', strtotime($data["To"])) : null,
                        'date_of_request' => $data['DateOfRequest'] != '' ? date('Y-m-d',
                            strtotime($data["DateOfRequest"])) : null,
                        'reason' => $data['Reasonforleave'],
                        'status' => $data['ApprovalStatus'],
                        'count_leave' => (double)$detail['LeaveCount'],
                        'session' => isset($detail['Session']) ? $detail['Session'] : '0',
                        'total' => (double)$data['Daystaken'],
                        'approval_status' => $data['ApprovalStatus'],
                    ]
                );
            } catch (\Exception $e) {
                $this->writeLog(BaseConstant::ERROR_TYPE, "SyncForm", 'ERROR : Lỗi ' . $e);
                continue;
            }
        }
    }

    public function syncFormFactor($data){
        $body['recordId'] = $data['Zoho_ID'];
        $responseData = $this->callZoho('forms/leave/getRecordByID', $body, true);
        foreach ($responseData['DayDetails'] as $date => $detail) {
            if (empty($detail)) {
                continue;
            }
            if (isset($data['action']) && $data['action'] == 'delete') {
                Leave::where('zoho_id', (string)$data["Zoho_ID"])->delete();
                continue;
            }
            try {
                Leave::updateOrCreate(
                    [
                        'zoho_id' => (string)$data['Zoho_ID'],
                        'date_leave' => date('Y-m-d', strtotime($date)),
                    ],
                    [
                        'employee' => $data['Employee_ID'],
                        'employee_id' => $data['Employee_ID.ID'],
                        'leave_type' => $data['Leavetype'],
                        'from' => $data['From'] != '' ? date('Y-m-d', strtotime($data["From"])) : null,
                        'to' => $data['To'] != '' ? date('Y-m-d', strtotime($data["To"])) : null,
                        'date_of_request' => $data['DateOfRequest'] != '' ? date('Y-m-d',
                            strtotime($data["DateOfRequest"])) : null,
                        'reason' => $data['Reasonforleave'],
                        'status' => $data['ApprovalStatus'],
                        'count_leave' => (double)$detail['LeaveCount'],
                        'session' => isset($detail['Session']) ? $detail['Session'] : '0',
                        'total' => (double)$data['Daystaken'],
                        'approval_status' => $data['ApprovalStatus'],
                    ]
                );
            } catch (\Exception $e) {
                $this->writeLog(BaseConstant::ERROR_TYPE, "SyncForm", 'ERROR : Lỗi ' . $e);
                continue;
            }
        }
    }

    public function syncFormFomular($data){
        dd($data);
        $body['recordId'] = $data['Zoho_ID'];
        $responseData = $this->callZoho('forms/leave/getRecordByID', $body, true);
        foreach ($responseData['DayDetails'] as $date => $detail) {
            if (empty($detail)) {
                continue;
            }
            if (isset($data['action']) && $data['action'] == 'delete') {
                Leave::where('zoho_id', (string)$data["Zoho_ID"])->delete();
                continue;
            }
            try {
                Leave::updateOrCreate(
                    [
                        'zoho_id' => (string)$data['Zoho_ID'],
                        'date_leave' => date('Y-m-d', strtotime($date)),
                    ],
                    [
                        'employee' => $data['Employee_ID'],
                        'employee_id' => $data['Employee_ID.ID'],
                        'leave_type' => $data['Leavetype'],
                        'from' => $data['From'] != '' ? date('Y-m-d', strtotime($data["From"])) : null,
                        'to' => $data['To'] != '' ? date('Y-m-d', strtotime($data["To"])) : null,
                        'date_of_request' => $data['DateOfRequest'] != '' ? date('Y-m-d',
                            strtotime($data["DateOfRequest"])) : null,
                        'reason' => $data['Reasonforleave'],
                        'status' => $data['ApprovalStatus'],
                        'count_leave' => (double)$detail['LeaveCount'],
                        'session' => isset($detail['Session']) ? $detail['Session'] : '0',
                        'total' => (double)$data['Daystaken'],
                        'approval_status' => $data['ApprovalStatus'],
                    ]
                );
            } catch (\Exception $e) {
                $this->writeLog(BaseConstant::ERROR_TYPE, "SyncForm", 'ERROR : Lỗi ' . $e);
                continue;
            }
        }
    }

}
