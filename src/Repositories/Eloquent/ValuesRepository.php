<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\Values;
use Dx\Payroll\Repositories\ValuesInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\AssignOp\Mod;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class ValuesRepository extends BaseRepository implements ValuesInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Values::class;
    }

    public function deleteRecords($formName, $ZohoID)
    {
        return $this->deleteWhere(['form_id' => $formName, 'zoho_id'=> $ZohoID]);
    }

    /**
     * Get records
     * @param
     * @return mixed
     */
    public function getRecords($formName = '', $offset = '', $limit = ''){
        $response = [];
        if(empty($limit)){
            $limit = 200;
        }
        dd($limit);
        $zohoForm = app(ZohoFormInterface::class)->findByField('form_name', $formName);
        if($zohoForm->isEmpty()){
            return "Missing Form Name";
        }
        if(empty($ZohoID)){
            return "Missing Zoho ID";
        }
    }

    /**
     * Get one record
     * @param
     * @return mixed
     */
    public function getRecordByID($formName = '', $ZohoID = ''){
        $response = [];
        $zohoForm = app(ZohoFormInterface::class)->findByField('form_name', $formName);
        if($zohoForm->isEmpty()){
            return "Missing Form Name";
        }
        if(empty($ZohoID)){
            return "Missing Zoho ID";
        }
        $response = $this->with('attribute', 'attribute.section')->findWhere(['form_id' => $zohoForm[0]->id, 'zoho_id'=> $ZohoID]);//->groupBy('section_id')
        return $this->formatRecords($zohoForm[0], $response);
    }


    /**
     * Get one record
     * @param
     * @return mixed
     */
    public function getRecordByIDClone($formName = '', $ZohoID = ''){
        DB::enableQueryLog();
        $response = [];
        $zohoForm = app(ZohoFormInterface::class)->findByField('form_name', $formName);
        if($zohoForm->isEmpty()){
            return "Missing Form Name";
        }
        if(empty($ZohoID)){
            return "Missing Zoho ID";
        }
        $response = $this->where('dx_values.form_id', $zohoForm[0]->id)
            ->where('dx_values.zoho_id', $ZohoID)
            ->join('dx_attributes', 'dx_attributes.id', '=', 'dx_values.attribute_id')
            ->join('dx_sections', 'dx_attributes.section_id', '=', 'dx_sections.id', 'left outer')->get();//->groupBy('section_id')

        return $this->formatRecords($zohoForm[0], $response);
    }

    /**
     * format data of records
     * @param
     * @return mixed
     */
    public function formatRecords($zohoForm = '', $data = []){
        $response = [];

        if (empty($data)) return $response;

        foreach ($data as $val){
            if (!empty($val->sections_name)) {
                $response['TabularSections'][$val->sections_name][$val->row_id][$val->attributes_label] = $this->castValue($val->type, $val->value);
            }
            $response[$val->attributes_label] = $this->castValue($val->type, $val->value);
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

}
