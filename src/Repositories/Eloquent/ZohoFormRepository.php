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

    public function formatFormConfig()
    {
        $response = [];
        if (!empty($this->all())) {
            foreach ($this->all() as $form) {
                $response['attendance']['getUserReport'] = 'attendance/getUserReport';
                $response['attendance']['getAttendanceEntries'] = 'attendance/getAttendanceEntries';
                $response['attendance']['getShiftConfiguration'] = 'attendance/getShiftConfiguration';
                $response['attendance']['bulkImport'] = 'attendance/bulkImport';
                $response['attendance']['getRegularizationRecords'] = 'attendance/getRegularizationRecords';
                $response['Leave']['getRecordByID'] = 'forms/leave/getRecordByID';
                $response['Leave']['getRecords'] = 'forms/leave/getRecords';
                $response['Leave']['updateRecord'] = 'forms/leave/updateRecord';
                $response['Leave']['insertRecord'] = 'forms/leave/insertRecord';
                $response['Leave']['getHolidays'] = 'leave/v2/holidays/get';
                $response['deleteRecords'] = 'deleteRecords';
                $response[$form->form_link_name]['components'] = 'forms/' . $form->form_link_name . '/components';
                $response[$form->form_link_name]['getDataByID'] = 'forms/' . $form->form_link_name . '/getDataByID';
                $response[$form->form_link_name]['getRecordByID'] = 'forms/' . $form->form_link_name . '/getRecordByID';
                $response[$form->form_link_name]['getRecords'] = 'forms/' . $form->form_link_name . '/getRecords';
                $response[$form->form_link_name]['insertRecord'] = 'forms/json/' . $form->form_link_name . '/insertRecord';
                $response[$form->form_link_name]['updateRecord'] = 'forms/json/' . $form->form_link_name . '/updateRecord';
                $response[$form->form_link_name]['deleteRecords'] = $form->form_link_name;
                if(!empty($form->formSection)){
                    foreach ($form->formSection as $sections){
                        $response[$form->form_link_name][$sections->section_label] = $sections->section_name;
                    }
                }
            }
        }
        return $response;
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
