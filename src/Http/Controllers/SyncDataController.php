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

    protected $zohoFor, $zohoController;
    public function __construct(ZohoFormInterface $zohoForm)
    {
        $this->zohoForm = $zohoForm;
        $this->zohoController = app(ZohoController::class);
        $this->notSynctoLocal = [
            "monthly_form_name" => "monthly_working_time",
            "payslip_form_name" => "payslip1"];
        $this->formMasterData = 'form_master_data';
        $this->syncToMasterData = [
            "employee_form_name" => "employee",
            "monthly_form_name" => "monthly_working_time",];
    }

    public function processSyncData(Request $arrInput){
        $config = app(RedisConfigFormInterface::class)->getConfig();
        if (empty($config)){
            return $this->sendError("No have Config");
        }

        if(isset($arrInput->master_data)){
            return $this->syncMasterData($config);
        }

        if(!isset($arrInput->form_name)){
            $allForm = $this->zohoForm->all();
        }else{
            $allForm = $this->zohoForm->findByField('form_link_name', $arrInput->form_name);
        }
        if(empty($allForm))
        {
            return $this->sendError("Empty Form");
        };

        foreach ($allForm as $form){
            $i = 0;
            while (true) {
                if(in_array($form->form_link_name, $this->notSynctoLocal)){
                    break;
                }
                if($arrInput->zoho_id == 'all')
                {
                    $body['sIndex'] = $i * 200;
                    $body['limit'] = 200;
                    $resData = $this->zohoController->getRecords($config[$form->form_link_name]['getRecords'], $body, true);
                }elseif (!empty($arrInput->zoho_id))
                {
                    //Delete record;
                    if (isset($arrInput->delete))
                    {
                        return $this->records->deleteRecords($form->id, $arrInput->zoho_id);
                    }

                    $resEmp = $this->zohoController->getRecordByID($arrInput->zoho_id, $config[$form->form_link_name]['getRecordByID']);
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
                    $resData = $this->zohoController->getRecords($config[$form->form_link_name]['getRecords'], $body, true);
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

    public function syncMasterData($config = []){
        // get zoho master data
        $index = 0;
        $allMasterData = [];
        do{
            $body['sIndex'] = $index * 200;
            $body['limit'] = 200;
            $arrData = $this->zohoController->getRecords($config[$this->formMasterData]['getRecords'], $body, true);
            if(!isset($arrData['errors'])){
                $allMasterData = array_merge($allMasterData, $arrData);
            }
            $index++;
        }while (!isset($arrData['errors']));

        // Sync data fields to zoho master data
        foreach ($this->syncToMasterData as $formSync){
            $listField = $this->zohoForm->getFieldOfForm($formSync);

            if($listField->isNotEmpty()){
                foreach ($listField as $field) {
                    //add
                    $zohoId = '';
                    $action = 'insertRecord';
                    $data['form_name']      = $field->form_name;
                    $data['form_label']     = $field->form_link_name;
                    $data['Table_Name']     = $field->section_name;
                    $data['Label_Table']    = $field->section_label;
                    $data['field_name']     = $field->field_name;
                    $data['field_label']    = $field->field_label;
                    if(!empty($allMasterData)){
                        foreach ($allMasterData as $keyData => $masterData){
                            if($field->field_label == $masterData['field_label'] && $field->section_label == $masterData['Label_Table']){
                                $action = 'updateRecord';
                                $zohoId = $masterData['Zoho_ID'];
                                unset($allMasterData[$keyData]);
                            }
                        }
                    }
                    $resMasterData = $this->zohoController->createdOrUpdated($config[$this->formMasterData][$action], $data, [], $zohoId);
                    Log::channel('dx')->info($resMasterData);
                }
            }
        }

        // delete record in master data zoho
        if(!empty($allMasterData)){
            foreach ($allMasterData as $masterData){
                $resMasterData = $this->zohoController->deleteRecords($this->formMasterData, $masterData['Zoho_ID']);
                Log::channel('dx')->info($resMasterData);
            }
        }

        //sync masterdata to local
        $arrSyncData = new Request;
        $arrSyncData['zoho_id']     = 'all';
        $arrSyncData['form_name']   = $this->formMasterData;
        $this->processSyncData($arrSyncData);
    }

}
