<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\ZohoRecord;
use Prettus\Repository\Eloquent\BaseRepository;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Illuminate\Support\Facades\DB;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class ZohoRecordRepository extends BaseRepository implements ZohoRecordInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return ZohoRecord::class;
    }

    /**
     * Delete records
     * @param
     * @return mixed
     */
    public function deleteRecords($formName, $ZohoID)
    {
        return $this->deleteWhere(['form_id' => $formName, 'zoho_id'=> strval($ZohoID)]);
    }

    /**
     * Get records
     * @return mixed
     */
    public function getRecords($formName, $offset = 0, $limit = 200, $params = [])
    {
        $zohoForm = $this->getFormByFormName($formName);
        if (is_null($zohoForm)) {
            throw new \ErrorException('Not found form name in database');
        }

        if (!empty($params)) {
            $attributes = $zohoForm->attributes;

            $paramsById = $attributes->reject( function($item) use ($params) {
                    return !isset($params[$item->label_name]);
            })->map( function ($item) use ($params) {
                if (isset($params[$item->label_name])) {
                    $item->value = $params[$item->label_name];
                    return $item;
                }
            });

            $response = $this->where('dx_zoho_records.form_id', $zohoForm->id);

            foreach ($paramsById as $param) {
                $response->whereHas('values', function ($query) use ($param) {
                    $query->where('field_id', $param->id);
                    switch ($param->value['searchOperator']) {
                        case 'Contains':
                            $query->where('value', 'like', '%' . strval($param->value['searchText']) . '%');
                            break;
                        case 'Between':
                            if (isset($param->value['format']) && $param->value['format'] = 'datetime') {
                                $query->whereBetween('date_time', $param->value['searchText']);
                            } else {
                                $query->whereBetween('date', $param->value['searchText']);
                            }
                            break;
                        default :
                            $query->where('value', strval($param->value['searchText']));
                    }
                });
            }

            $responseSearch = $response->skip($offset)->take($limit)->get();
            if ($responseSearch->isEmpty()) {
                return  $this->formatRecords(collect([]), $zohoForm);
            }

            $responseSearch = $responseSearch->keyBy('zoho_id')->keys()->toArray();

            $response = $this->whereIn('zoho_id', $responseSearch)->with(['values' => function ($query) {
                $query->join('dx_zoho_record_fields', 'dx_zoho_record_fields.id', '=', 'field_id')
                    ->join('dx_zoho_sections', 'dx_zoho_sections.id', '=', 'dx_zoho_record_fields.section_id', 'left outer');
            }])->skip($offset)->take($limit)->get();

            return  $this->formatRecords($response, $zohoForm);
        }

        $response = $this->where('dx_zoho_records.form_id', $zohoForm->id)->with(['values' => function ($query) {
            $query->join('dx_zoho_record_fields', 'dx_zoho_record_fields.id', '=', 'field_id')
                ->join('dx_zoho_sections', 'dx_zoho_sections.id', '=', 'dx_zoho_record_fields.section_id', 'left outer');
        }])->skip($offset)->take($limit)->get();

        return  $this->formatRecords($response, $zohoForm);
    }

    /**
     * Get one record by zoho ID
     * @return mixed
     */
    public function getRecordByZohoID($formName, $ZohoID)
    {
        $zohoForm = $this->getFormByFormName($formName);
        if(is_null($zohoForm)){
            throw new \ErrorException('Not found form name in database');
        }

        $response = $this->where('dx_zoho_records.form_id', $zohoForm->id)->where('dx_zoho_records.zoho_id', strval($ZohoID))
            ->with(['values' => function ($query) {
                $query->join('dx_zoho_record_fields', 'dx_zoho_record_fields.id', '=', 'field_id')
                    ->join('dx_zoho_sections', 'dx_zoho_sections.id', '=', 'dx_zoho_record_fields.section_id', 'left outer');
            }])->first();

        return $this->formatRecord($response, $zohoForm);
    }

    /**
     * format data of records
     * @param
     * @return mixed
     */
    public function formatRecord($recordData, $formDetails)
    {
        $response = [];

        if (is_null($recordData)) return $response;

        return $this->formatRecordToArray($recordData, $formDetails);;
    }

    /**
     * format data of records
     * @param
     * @return mixed
     */
    public function formatRecords($recordDatas, $formDetails)
    {
        $response = [];
        if (empty($recordDatas)) return $response;

        foreach ($recordDatas as $recordData) {
            $response[] = $this->formatRecordToArray($recordData, $formDetails);
        }
        
        return $response;
    }

    private function formatRecordToArray($recordData, $formDetails)
    {
        $response['Zoho_ID'] = $recordData->zoho_id;

        $formGeneralAttributes = $formDetails->attributes->keyBy('label_name');

        foreach ($recordData->values as $val) {
            if (!empty($val->section_name)) {
                $response['tabularSections'][$val->section_name][$val->row_id][$val->label_name] = $this->castValue($val->comp_type, $val->value);
                continue;
            }

            $response[$val->label_name] = $this->castValue($val->comp_type, $val->value);

            $formGeneralAttributes->forget($val->label_name);
        }
        
        foreach ($formGeneralAttributes as $attribute) {
            $response[$attribute->label_name] = $attribute->autofillvalue;
        }

        return $response;
    }

    private function castValue($type, $value)
    {
        switch ($type) {
            case $type == 'Number' :
                return intval($value);
            default :
                return $value;
        }
    }
    
    private function getFormByFormName($formName)
    {
        return app(ZohoFormInterface::class)->where('form_link_name', $formName)
        ->with(['attributes:id,label_name,autofillvalue,form_id,section_id'])
        ->with(['sections'])
        ->with(['sections.attributes:id,label_name,autofillvalue,form_id,section_id'])
        ->first();
    }
}
