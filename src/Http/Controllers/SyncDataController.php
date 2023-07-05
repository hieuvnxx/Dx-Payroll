<?php

namespace Dx\Payroll\Http\Controllers;

use Illuminate\Http\Request;
use Dx\Payroll\Models\Sections;
use Dx\Payroll\Models\Values;
use Dx\Payroll\Models\OverTimeSettings;
use Dx\Payroll\Models\TaxSettings;
use Dx\Payroll\Repositories\ValuesInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Dx\Payroll\Http\Controllers\ZohoController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class SyncDataController
{
    public $values;
    public function __construct(ValuesInterface $values)
    {
        $this->values = $values;
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
            $allForm = $zohoForm->findByField('form_name', $arrInput->form_name);
        }
        if(empty($allForm))
        {
            return $this->sendError("Empty Form");
        };
        $zoho = app(ZohoController::class);
        DB::enableQueryLog();
        foreach ($allForm as $form){
//
            dd(base64_encode(file_get_contents(storage_path('eSign.pdf'))));
//            $x =  $this->values->getRecords($arrInput->form_name, 1);getRecordByIDClone
            $x =  $this->values->getRecordByIDClone($arrInput->form_name, $arrInput->zoho_id);
            dd(DB::getQueryLog(), $x);

            $x =  $this->values->getRecordByID($arrInput->form_name, $arrInput->zoho_id);
            dd(DB::getQueryLog(), $x);
            dd($response->toArray());
            dd($x);
            $i = 0;
            while (true) {
                if (!empty($arrInput->zoho_id))
                {
                    //Delete record;
                    if (isset($arrInput->delete))
                    {
                        return $this->values->deleteRecords($form->id, $arrInput->zoho_id);
                    }
                    $resEmp = $zoho->getRecordByID($arrInput->zoho_id, $config[$form->form_name]['getRecordByID']);
                    if(array_key_exists('errors', $resEmp)){
                        $resData = $resEmp;
                    }else{
                        $resEmp['Zoho_ID'] = $arrInput->zoho_id;
                        $resData[] = $resEmp;
                    }
                }else{
                    $body['sIndex'] = $i * 200;
                    $body['limit'] = 200;
                    $body['searchParams'] = "{searchField: 'ModifiedTime', searchOperator: 'Yesterday'}";
                    $resData = $zoho->getRecords($config[$form->form_name]['getRecords'], $body, true);
                }
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
                                        try{
                                            Values::updateOrCreate(
                                                [
                                                    'form_id' => $form->id,
                                                    'attribute_id' => $attribute->id,
                                                    'zoho_id' => $data['Zoho_ID'],
                                                    'section_id' => '0',
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
                                    if($attribute->section_id !== "0"){
                                        $arrAttributesForm[$attribute->section_id][] = $attribute->attributes_label;
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
                                                    $sectionID = Sections::updateOrCreate(
                                                        [
                                                            'id' => $ky,
                                                            'form_id' => $form->id
                                                        ],[
                                                            'sections_name' => $tabularName,
                                                        ]
                                                    );
                                                }
                                            }
                                            // Sync data in tabular
                                            foreach ($tabularValues as $tabularValue) {
                                                foreach ($tabularValue as $keys => $vals) {
                                                    foreach ($form->attribute as $attribute){
                                                        if($keys == $attribute->attributes_label){
                                                            Values::updateOrCreate(
                                                                [
                                                                    'form_id' => $form->id,
                                                                    'attribute_id' => $attribute->id,
                                                                    'zoho_id' => $data['Zoho_ID'],
                                                                    'section_id' => $sectionID->id,
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
}
