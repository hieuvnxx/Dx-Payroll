<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Models\Sections;
use Dx\Payroll\Models\Values;
use Dx\Payroll\Models\ZohoForm;
use Dx\Payroll\Repositories\Eloquent\SectionsRepository;
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

    public function processSyncData(Request $arrInput){
        $config = app(RedisConfigFormInterface::class)->getConfig();
        if (empty($config)) {
            return $this->sendError("No have Config");
        }
        $zohoForm = app(ZohoFormInterface::class);
        $allForm = $zohoForm->all();
        if(empty($allForm)) return 0;
        $zoho = app(ZohoController::class);
        foreach ($allForm as $form){
            $i = 0;
            if($form->form_name != "contract_management"){
                continue;
            }
            $sectionNull = app(SectionsRepository::class)->findWhere(['form_id' => $form->id, 'sections_name' => null]);
            while (true) {
                if (isset($arrInput->zoho_id))
                {
                    $resEmp = $zoho->getRecordByID($arrInput->zoho_id, $config[$form->form_name]['getRecordByID']);
                    if(array_key_exists('errors', $resEmp)){
                        $resData = $resEmp;
                    }else{
                        $resEmp['Zoho_ID'] = $arrInput->zoho_id;
                        $resData[] = $resEmp;
                    }
                }else{
                    $resData = $zoho->getRecords($config[$form->form_name]['getRecords'], ["sIndex" => $i* 200, "limit" => 1]);
                }
                //Delete record;
//                if (isset($arrInput->delete) && isset($arrInput->zoho_id))
//                {
//                    unset($resData);
//                    $resData[] = ['Zoho_ID' => $arrInput->zoho_id, 'Delete' => $arrInput->delete];
//                }
                if(empty($resData) || array_key_exists('errors', $resData) || (!empty($arrInput->zoho_id) && $i == 1)) break;
                if(!empty($resData))
                {
                    foreach ($resData as $data){
                        foreach ($data as $key => $value){
                            if($key != "tabularSections"){
                                foreach ($form->attribute as $attribute){
                                    if($key === $attribute->attributes_label){
                                        if($attribute->type == "Lookup"){
                                            $valueIs = $data[$key.'.ID'] ?? $data[$key.'.id'] ?? $data[$key];
                                        }else{
                                            $valueIs = $value;
                                        }
                                        $sectionId = $attribute->section_id ?? '0';
                                        try{
                                            Values::updateOrCreate(
                                                [
                                                    'form_id' => $form->id,
                                                    'attribute_id' => $attribute->id,
                                                    'section_id' => $sectionId,
                                                    'zoho_id' => $data['Zoho_ID']
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
                                foreach ($data['tabularSections'] as $keys => $values) {
                                    if (!empty($values[0])) {
                                        foreach ($values as $value) {
                                            $arrAttributes = [];
                                            foreach ($value as $attributes => $val) {
                                                if (!str_contains(strtolower($attributes), ".id") && !str_contains(strtolower($attributes), ".displayvalue") && $attributes !== 'tabular.ROWID') {
                                                    $arrAttributes[] = $attributes;
                                                }

//                                                dd($keys, $values, $value, $attributes , $val);
                                            }
                                            sort($arrAttributes);
                                            if($sectionNull->isNotEmpty()){
                                                $arrAttributesForm = [];
                                                foreach ($form->attribute as $attribute){
                                                    if($attribute->section_id !== "0"){
                                                        $arrAttributesForm[$attribute->section_id][] = $attribute->attributes_label;
                                                        sort($arrAttributesForm[$attribute->section_id]);
                                                    }
                                                }
                                                foreach ($arrAttributesForm as $ky => $arrAttribute){
                                                    if($arrAttribute == $arrAttributes){
                                                        Sections::updateOrCreate(
                                                            [
                                                                'id' => $ky,
                                                                'form_id' => $form->id
                                                            ],[
                                                                'sections_name' => $keys,
                                                            ]
                                                        );
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                break;
                if($i ==0){
                    return;
                }
//                $i++;
            }
        }
        echo 'End Sync '.$arrInput->form_name."\n";
    }

    public function syncEmployee($data)
    {
        dd($data);
    }

    public function syncOT($data)
    {
        if(empty($data)){
            return 0;
        }
        if(isset($data['Delete'])){
            $info = OverTime::where('zoho_id', (string)$data['Zoho_ID'])->first();
            if ($info ) {
                $info->delete();
            }
            return 1;
        }
        OverTime::updateOrCreate(
            [   'zoho_id'       => (string)$data['Zoho_ID']],
            [
                'request_id'    => $data['request_id'] ?? null,
                'employee_id'   => $data['employee_id'] ?? null,
                'employee_name' => $data['employee_name'] ?? null,
                'project_task'  => $data['project_task'] ?? null,
                'description'   => $data['description'] ?? null,
                'reason'        => $data['reason'] ?? null,
                'allowance'     => $data['allowance'] ?? null,
                'date'          => !empty($data['date']) ? date('Y-m-d', strtotime($data['date'])) : null,
                'type'          => $data['type'] ?? null,
                'from'          => $data['from'] ?? null,
                'to'            => $data['to'] ?? null,
                'hour'          => $data['hour'] ?? null,
                'status'        => $data['status'] ?? null
            ]
        );
    }

    public function syncSetting($data)
    {
        if(empty($data)){
            return 0;
        }
        if(isset($data['Delete'])){
            $info = GeneralSettings::where('zoho_id', (string)$data['Zoho_ID'])
                ->with('bonusSetting', 'levelSetting', 'overTimeSetting', 'taxSetting')->first();
            if ($info ) {
                $info->bonusSetting()->delete();
                $info->levelSetting()->delete();
                $info->overTimeSetting()->delete();
                $info->taxSetting()->delete();
                $info->delete();
            }
            return 1;
        }
        GeneralSettings::updateOrCreate(
            [   'zoho_id'           => (string)$data['Zoho_ID']],
            [
                'from'              => $data['from_date'] ?? null,
                'to'                => $data['to_date'] ?? null,
                'working_day'       => $data['standard_working_hour'] ?? null,
                'haft_working_day'  => $data['standard_working_hour_haftday'] ?? null,
                'deduction'         => $data['self_deduction_amount'] ?? null,
                'dependent'         => $data['dedependent_deduction_amount'] ?? null
            ]
        );
        foreach ($data['tabularSections'] as $key => $tabular) {
            if (empty($tabular[0])) continue;
            foreach ($tabular as $item) {
                if (!isset($item['tabular.ROWID'])) {
                    continue;
                }
                if ($key == "Quy định tính thuế TNCN") {
                    TaxSettings::updateOrCreate(
                        [   'zoho_id'   => (string)$data['Zoho_ID'],
                            'row_id'    => (string)$item["tabular.ROWID"]
                        ],
                        [
                            'level'     => $item["Level1"] ?? null,
                            'from'      => $item["From_VND"] ?? null,
                            'to'        => $item["To_VND"] ?? null,
                            'rate'      => $item["Tax_Rate1"] ?? null
                        ]
                    );
                }
                if ($key == "Chính sách lương cơ bản và trợ cấp") {
                    LevelSettings::updateOrCreate(
                        [   'zoho_id'   => (string)$data['Zoho_ID'],
                            'row_id'    => (string)$item["tabular.ROWID"]
                        ],
                        [
                            'level'             => $item['Level'] ?? null,
                            'basic_salary'      => $item['basic_salary'] ?? null,
                            'lunch_allowance'   => $item['lunch_allowance'] ?? null,
                            'travel_allowance'  => $item['travel_allowance'] ?? null,
                            'phone_allowance'   => $item['phone_allowance'] ?? null,
                            'other_allowance'   => $item['other_allowance'] ?? null
                        ]
                    );
                }
                if ($key == "Quy định làm ngoài giờ") {
                    OverTimeSettings::updateOrCreate(
                        [   'zoho_id'   => (string)$data['Zoho_ID'],
                            'row_id'    => (string)$item["tabular.ROWID"]
                        ],
                        [
                            'type'          => $item['OT_Type'] ?? null,
                            'day_rate'      => $item['DayTime_Tax_rate'] ?? null,
                            'night_rate'    => $item['NightTime_Tax_rate'] ?? null
                        ]
                    );
                }
                if ($key == "Quy định thưởng") {
                    BonusSettings::updateOrCreate(
                        [   'zoho_id'   => (string)$data['Zoho_ID'],
                            'row_id'    => (string)$item["tabular.ROWID"]
                        ],
                        [
                            'type'              => $item['bonus_type'] ?? null,
                            'date'              => !empty($item['date']) ? date('Y-m-d', strtotime($item['date'])) : null,
                            'probation_amount'  => $item['probation_amount'] ?? null,
                            'amount'            => $item['amount'] ?? null
                        ]
                    );
                }
            }
        }
    }

    public function syncFactorMasterData($data)
    {
        if(empty($data)){
            return 0;
        }
        if(isset($data['Delete'])){
            $info = FactorMasterData::where('zoho_id', (string)$data['Zoho_ID'])->first();
            if ($info ) {
                $info->delete();
            }
            return 1;
        }
        FactorMasterData::updateOrCreate(
            [   'zoho_id'       => (string)$data['Zoho_ID']],
            [
                'factor'        => $data['factor'] ?? null,
                'abbreviation'  => $data['abbreviation'] ?? null,
                'type'          => $data['type'] ?? null,
                'form_name'     => $data['form_name'] ?? null,
                'field_name'    => $data['field_name'] ?? null,
                'note'          => $data['note'] ?? null,
            ]
        );
    }

    public function syncFormula($data)
    {
        if(empty($data)){
            return 0;
        }
        if(isset($data['Delete'])){
            $info = Fomular::where('zoho_id', (string)$data['Zoho_ID'])->first();
            if ($info ) {
                $info->delete();
            }
            return 1;
        }
        Fomular::updateOrCreate(
            [   'zoho_id'   => (string)$data['Zoho_ID']],
            [
                'factor_1'  => $data['zoho_id'] ?? null,
                'factor_2'  => $data['factor_1'] ?? null,
                'factor_3'  => $data['factor_2'] ?? null,
                'factor_4'  => $data['factor_3'] ?? null,
                'factor_5'  => $data['factor_4'] ?? null,
                'factor_6'  => $data['factor_5'] ?? null,
                'factor_7'  => $data['factor_6'] ?? null,
                'factor_8'  => $data['factor_7'] ?? null,
                'factor_9'  => $data['factor_8'] ?? null,
                'factor_10' => $data['factor_9'] ?? null,
                'factor_11' => $data['factor_10'] ?? null,
                'factor_12' => $data['factor_11'] ?? null,
                'factor_13' => $data['factor_12'] ?? null,
                'factor_14' => $data['factor_13'] ?? null,
                'field'     => $data['factor_14'] ?? null,
                'fomular'   => $data['field'] ?? null,
                'from_date' => !empty($data['date']) ? date('Y-m-d', strtotime($data['date'])) : null,
                'to_date'   => !empty($data['to_date']) ? date('Y-m-d', strtotime($data['to_date'])) : null,
                'contract_type' => $data['to_date'] ?? null,
                'type'      => $data['contract_type'] ?? null,
                'department'=> $data['type'] ?? null
            ]
        );
    }

    public function syncFormMasterData($data)
    {
        if(empty($data)){
            return 0;
        }
        if(isset($data['Delete'])){
            $info = FormMasterData::where('zoho_id', (string)$data['Zoho_ID'])->first();
            if ($info ) {
                $info->delete();
            }
            return 1;
        }
        FormMasterData::updateOrCreate(
            [   'zoho_id'       => (string)$data['Zoho_ID']],
            [
                'field_name'    => $data['field_name'] ?? null,
                'form_name'     => $data['form_name'] ?? null,
            ]
        );
    }
}
