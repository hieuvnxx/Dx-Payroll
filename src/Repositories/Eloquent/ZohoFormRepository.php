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
        $allForm = ZohoForm::all();
        if (!empty($allForm)) {
            foreach ($allForm as $value) {
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
                $response[$value->form_name]['components'] = 'forms/' . $value->form_name . '/components';
                $response[$value->form_name]['getDataByID'] = 'forms/' . $value->form_name . '/getDataByID';
                $response[$value->form_name]['getRecordByID'] = 'forms/' . $value->form_name . '/getRecordByID';
                $response[$value->form_name]['getRecords'] = 'forms/' . $value->form_name . '/getRecords';
                $response[$value->form_name]['insertRecord'] = 'forms/json/' . $value->form_name . '/insertRecord';
                $response[$value->form_name]['updateRecord'] = 'forms/json/' . $value->form_name . '/updateRecord';
                $response[$value->form_name]['deleteRecords'] = $value->form_name;
                if(!empty($value->formSection)){
                    foreach ($value->formSection as $sections){
                        $response[$value->form_name][$sections->sections_id] = $sections->sections_id;
                    }
                }
            }
        }
        return $response;
    }

}
