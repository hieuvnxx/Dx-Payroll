<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\ZohoForm;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class ZohoFormRepository extends BaseRepository implements ZohoFormInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return ZohoForm::class;
    }

    public function getFieldOfForm($formName)
    {
        $response = [];
        $arrField = $this->where('form_link_name', $formName)
            ->join('dx_zoho_record_fields', 'dx_zoho_record_fields.form_id', '=', 'dx_zoho_forms.id')
            ->join('dx_zoho_sections', 'dx_zoho_sections.id', '=', 'dx_zoho_record_fields.section_id', 'left outer')
            ->get();
        if(!empty($arrField)){
            $response = $arrField;
        }
        return $response;
    }

}
