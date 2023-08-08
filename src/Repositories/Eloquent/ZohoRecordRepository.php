<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\ZohoRecord;
use Prettus\Repository\Eloquent\BaseRepository;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;

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
    public function getRecords($formName, $offset = 0, $limit = 200)
    {
        $response = [];

        $zohoForm = $this->getFormByFormName($formName);
        if ($zohoForm->isEmpty()) {
            throw new \ErrorException('Not found form name in database');
        }

        $zohoForm = $zohoForm[0];

        $response = $this->where('dx_zoho_records.form_id', $zohoForm->id)
            ->with(['values' => function ($query) {
            $query->join('dx_zoho_record_fields', 'dx_zoho_record_fields.id', '=', 'field_id')
                ->join('dx_zoho_sections', 'dx_zoho_sections.id', '=', 'dx_zoho_record_fields.section_id', 'left outer');
        }])->skip($offset)->take($limit)->get();

        return $this->formatRecords($response);
    }

    /**
     * Get one record by zoho ID
     * @return mixed
     */
    public function getRecordByZohoID($formName, $ZohoID)
    {
        $zohoForm = $this->getFormByFormName($formName);
        if($zohoForm->isEmpty()){
            throw new \ErrorException('Not found form name in database');
        }

        $zohoForm = $zohoForm[0];

        $response = $this->where('dx_zoho_records.form_id', $zohoForm->id)->where('dx_zoho_records.zoho_id', $ZohoID)
            ->with(['values' => function ($query) {
                $query->join('dx_zoho_record_fields', 'dx_zoho_record_fields.id', '=', 'field_id')
                    ->join('dx_zoho_sections', 'dx_zoho_sections.id', '=', 'dx_zoho_record_fields.section_id', 'left outer');
            }])->get();

        return $this->formatRecords($response);
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


    /**
     * search one record
     * @param
     * @return mixed
     */
    public function searchRecords($formName = '', $field = '', $value = null)
    {
        $response = [];
        $zohoForm = app(ZohoFormInterface::class)->findByField('form_link_name', $formName);
        if($zohoForm->isEmpty()){
            return "Missing Form Name";
        }

        $result = $this->where('dx_zoho_records.form_id', $zohoForm[0]->id)
            ->join('dx_zoho_record_values', 'dx_zoho_record_values.record_id', '=', 'dx_zoho_records.id')
            ->join('dx_zoho_record_fields', 'dx_zoho_record_fields.id', '=', 'dx_zoho_record_values.field_id')
            ->where('field_label', $field)->where('value', $value)
            ->get();
        if(!$result->isEmpty()){
            $response = $this->getRecordByID($formName, $result[0]->zoho_id);
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
        return app(ZohoFormInterface::class)->findByField('form_link_name', $formName);
    }
}
