<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\ZohoRecord;
use Prettus\Repository\Eloquent\BaseRepository;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Illuminate\Support\Env;
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
        return $this->deleteWhere(['form_id' => $formName, 'zoho_id'=> $ZohoID]);
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

        $response = $this->where('dx_zoho_records.form_id', $zohoForm->id);
        
        /*
        * pending update for search multiple params and criteria
        * suggest writing on new function
        */
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $response->whereHas('values', function($query) use ($key, $value) {
                    $query->with(['courses.fields' => function($query) use ($key, $value) {
                        $query->where('field_label', $key);
                    }])->where('value', strval($value));
                });
            }
        }
        
        $response->with(['values' => function ($query) {
            $query->join('dx_zoho_record_fields', 'dx_zoho_record_fields.id', '=', 'field_id')
                ->join('dx_zoho_sections', 'dx_zoho_sections.id', '=', 'dx_zoho_record_fields.section_id', 'left outer');
        }]);

        return  $this->formatRecords($response->skip($offset)->take($limit)->get());
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

        return $this->formatRecord($response);
    }

    /**
     * format data of records
     * @param
     * @return mixed
     */
    public function formatRecord($origin)
    {
        $response = [];

        if (is_null($origin)) return $response;

        foreach ($origin->values as $val){
            $response['Zoho_ID'] = $origin->zoho_id;
            $response[$val->field_label] = $this->castValue($val->type, $val->value);
            if (!empty($val->section_name)) {
                $response['TabularSections'][$val->section_name][$val->row_id][$val->field_label] = $this->castValue($val->type, $val->value);
            }
        }

        return $response;
    }

    /**
     * format data of records
     * @param
     * @return mixed
     */
    public function formatRecords($origin)
    {
        $response = [];
        if (empty($origin)) return $response;

        $index = 0;
        foreach ($origin as $data){
            foreach ($data->values as $val){
                $response[$index]['Zoho_ID'] = $data->zoho_id;
                $response[$index][$val->field_label] = $this->castValue($val->type, $val->value);
                if (!empty($val->section_name)) {
                    $response[$index]['TabularSections'][$val->section_name][$val->row_id][$val->field_label] = $this->castValue($val->type, $val->value);
                }
            }
            $index++;
        }
        return $response;
    }

    public function castValue($type, $value)
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
        return app(ZohoFormInterface::class)->where('form_link_name', $formName)->first();
    }
}
