<?php


namespace Dx\Payroll\Http\Controllers\Api\Payroll;

use Carbon\Carbon;
use Dx\Payroll\Http\Controllers\Api\Payroll\PayrollController;
use Dx\Payroll\Http\Requests\ApiWorkingTimeAll;
use Dx\Payroll\Http\Requests\ApiWorkingTimeByCode;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Jobs\Payroll\PushMonthyWorkingTimePerEmployeeToZoho;
use Dx\Payroll\Models\DateDimension;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Support\Env;

/**
 * insert database zoho form
 */
class MonthlyWorkingTimeController extends PayrollController
{
    protected $zohoLib;
    protected $zohoForm;
    protected $zohoRecord;
    protected $zohoRecordValue;
    
    public function __construct(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord, ZohoRecordValueInterface $zohoRecordValue)
    {
        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $this->zohoForm = $zohoForm;
        $this->zohoRecord = $zohoRecord;
        $this->zohoRecordValue = $zohoRecordValue;
    }

    public function processAll(ApiWorkingTimeAll $request)
    {
        $month   = $request->month;
        $employeeFormLinkName       = Env::get('EMPLOYEE_FORM_LINK_NAME');
        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');

        $offset = 0;
        $limit  = 100;
        
        $arrEmpCode = [];
        while (true) {
            $employees = $this->zohoRecord->getRecords($employeeFormLinkName, $offset, $limit, ['Employeestatus' => "Active"]);
            if (empty($employees)) {
                break;
            }

            foreach($employees as $employee) {
                $arrEmpCode[] = $employee[$employeeIdNumberFieldLabel];
                
                $data = [
                    'employee' => $employee,
                    'month' => $month
                ];
                PushMonthyWorkingTimePerEmployeeToZoho::dispatch($data);
            }

            $offset += $limit;
        }

        return $this->sendResponse($request, 'Successfully.', [ 'empCodes' => $arrEmpCode, 'total' => count($arrEmpCode)]);
    }

    /**
     * 
     */
    public function processByCode(ApiWorkingTimeByCode $request)
    {
        $employeeFormLinkName       = Env::get('EMPLOYEE_FORM_LINK_NAME');
        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');
        $constantConfigFormLinkName = Env::get('PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME');
        $monthlyWorkingTimeFormLinkName = Env::get('PAYROLL_MONTHY_WORKING_TIME_FORM_LINK_NAME');

        $empCode = $request->code;
        $month   = $request->month;
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;

        $formEav = $this->zohoForm->has('attributes', 'sections', 'sections.attributes')->with('attributes', 'sections', 'sections.attributes')->where('form_link_name', $monthlyWorkingTimeFormLinkName)->first();
        if (is_null($formEav)) {
            return $this->sendError($request, 'Something error with monthly working time form in database.');
        }

        $constantConfigs = $this->zohoRecord->getRecords($constantConfigFormLinkName);
        if (empty($constantConfigs)) {
            return $this->sendError($request, 'Constant configuration empty in database. Please re-generate constant configuration.');
        }

        $constantConfig = $constantConfigs[0];

        list($fromSalary, $toSalary) = payroll_range_date($month, $constantConfig['from_date'], $constantConfig['to_date']);

        // get employee informations by code
        $employee = $this->zohoRecord->getRecords($employeeFormLinkName, 0, 200, [$employeeIdNumberFieldLabel => $empCode])[0];

        // fetch all punch in - out
        /*
        * pending get data from database
        */
        $employeeDataPunch = $this->zohoLib->getAttendanceByEmployee($empCode, $fromSalary, $toSalary);

        // get punch shift by employee data punch range payslip
        /*
        * pending get data from database
        */
        $dataShiftConfig = $this->zohoLib->getShiftConfigurationByEmployee($empCode, $fromSalary, $toSalary, $employeeDataPunch);

        // get all leave
        /*
        * pending get data from database
        */
        $leaves = $this->getLeaveWorking($empCode, $fromSalary, $toSalary);

        // fetch all overtime request range payslip
        /*
        * pending get data from database
        */
        $overtimes = $this->getOverTime($empCode, $fromSalary, $toSalary);

        // Kiểm tra xem đã tồn tại monthly working report chưa
        $existMonthlyIds = [];
        $monthlyWorkingTimeExistZoho = $this->zohoLib->getRecords($monthlyWorkingTimeFormLinkName, 0, 200, array(
            [
                'searchField' => 'employee',
                'searchOperator' => 'Contains',
                'searchText' => $empCode,
            ],
            [
                'searchField' => 'salary_period',
                'searchOperator' => 'Is',
                'searchText' => $monthly,
            ],
        ));

        list($tabularData, $paidLeave, $holidayCount,
        $standardWorkingTime, $standardWorkingDay, $standardWorkingDayProbation,
        $otMealAllowance, $weekdayHour, $weekNight, $weekendHour,
        $weekendNight, $holidayHour, $holidayNight) = $this->processUpdateData($dataShiftConfig, $constantConfig, $employee, $leaves, $overtimes, $formEav);

        $totalWorkingDays = $standardWorkingDay + $standardWorkingDayProbation;

        $inputData = [];
        $inputData['employee'] = $employee['Zoho_ID'];
        $inputData['salary_period'] = $monthly;
        $inputData['code'] = $code;
        $inputData['standard_working_time'] = $standardWorkingTime;
        $inputData['standard_working_day'] = $standardWorkingDay;
        $inputData['standard_working_day_probation'] = $standardWorkingDayProbation;
        $inputData['total_working_days'] = $totalWorkingDays;
        $inputData['holiday_count'] = $holidayCount;
        $inputData['paid_leave'] = $paidLeave;
        $inputData['total_salary_working_day'] = $totalWorkingDays + $holidayCount + $paidLeave;
        $inputData['ot_meal_allowance'] = $otMealAllowance;
        $inputData['weekday1'] = $weekdayHour;
        $inputData['week_night1'] = $weekNight;
        $inputData['weekend1'] = $weekendHour;
        $inputData['weekend_night1'] = $weekendNight;
        $inputData['holiday_hour1'] = $holidayHour;
        $inputData['holiday_night1'] = $holidayNight;

        /* remove exist record in zoho if exist */
        if (!empty($monthlyWorkingTimeExistZoho[0])) {
            $existMonthlyIds = collect($monthlyWorkingTimeExistZoho)->map(function ($data) {
                return $data['Zoho_ID'];
            })->toArray();


            $existToUpdateZohoId = array_shift($existMonthlyIds);
            if (!empty($existMonthlyIds)) {
                $existMonthlyStringIds = implode(',', $existMonthlyIds);
                $rspDeleteMonthlyWorkingTimeExistZoho = $this->zohoLib->deleteRecords($monthlyWorkingTimeFormLinkName, $existMonthlyStringIds);
            }

            foreach ($monthlyWorkingTimeExistZoho as $monthlyWorkingTime) {
                if ($monthlyWorkingTime['Zoho_ID'] == $existToUpdateZohoId) {
                    $this->removeExistTabularZoho($tabularData, $monthlyWorkingTime, $formEav);
                }
            }

            if (empty($tabularData)) {
                return $this->sendError($request, 'Something error. Can not generate attendance detail. empCode: ' . $empCode);
            }

            $rspUpdate = $this->zohoLib->updateRecord($monthlyWorkingTimeFormLinkName, $inputData, $tabularData, $existToUpdateZohoId, 'yyyy-MM-dd');
            if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
                return $this->sendError($request, 'Something error. Can not update attendance detail to record monthy working time with id : '. $existToUpdateZohoId, [$inputData, $tabularData]);
            }

            return $this->sendResponse($request, 'Successfully.', [$rspDeleteMonthlyWorkingTimeExistZoho ?? [], $rspUpdate]);
        }

        if (empty($tabularData)) {
            return $this->sendError($request, 'Something error. Can not generate attendance detail. empCode: ' . $empCode);
        }

        $rspInsert = $this->zohoLib->insertRecord($monthlyWorkingTimeFormLinkName, $inputData, 'yyyy-MM-dd');
        if (!isset($rspInsert['result']) || !isset($rspInsert['result']['pkId'])) {
            return $this->sendError($request, 'Something error. Can not insert new record monthy working time in to zoho.', $inputData);
        }

        $zohoId = $rspInsert['result']['pkId'];
            
        $rspUpdate = $this->zohoLib->updateRecord($monthlyWorkingTimeFormLinkName, $inputData, $tabularData, $zohoId, 'yyyy-MM-dd');
        if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
            return $this->sendError($request, 'Something error. Can not update attendance detail to record monthy working time with id : '. $zohoId, $inputData);
        }

        return $this->sendResponse($request, 'Successfully.', [$rspInsert, $rspUpdate]);
    }

    /**
     * return all leave request approved
     */
    private function getLeaveWorking($empCode, $startDate, $endDate)
    {
        $response = [];

        $leaves = $this->zohoLib->searchLeaveWorking($empCode, $startDate, $endDate);
        if (isset($leaves['errors']) && isset($leaves['errors']['code']) && isset($leaves['status']) && $leaves['status'] != 0) return $response;

        if (!empty($leaves)) {

            $startDate = Carbon::createFromFormat('Y-m-d', $startDate);
            $endDate = Carbon::createFromFormat('Y-m-d', $endDate);
            foreach ($leaves as $leave) {
                if (str_contains(strtolower($leave['Leavetype']), "unpaid")) continue;

                /*
                * Pending get data by database
                */
                $detailLeave = $this->zohoLib->getRecordByID('leave', $leave['Zoho_ID']);

                if (isset($leaves['errors']) && isset($leaves['errors']['code']) && isset($leaves['status']) && $leaves['status'] != 0) continue;
                if ($detailLeave['ApprovalStatus'] != 'Approved' || empty($detailLeave['DayDetails'])) continue;

                foreach ($detailLeave['DayDetails'] as $day => $val) {
                    if ($val['LeaveCount'] == '0.0') continue;

                    $day = Carbon::createFromFormat('d-F-Y', $day)->format('Y-m-d');
                    $day = Carbon::createFromFormat('Y-m-d', $day);

                    if ($day->gte($startDate) && $day->lte($endDate)) {

                        $dayString = $day->format('Y-m-d');
                        if (isset($response[$dayString]['leave_day'])) {
                            $response[$dayString]['leave_day'] += $val['LeaveCount'];
                        } else {
                            $response[$dayString]['leave_day'] = $val['LeaveCount'];
                            $response[$dayString]['leave_type'] = $leave['Leavetype'];
                        }

                    }

                }
            }
        }

        return $response;
    }

    /**
    * return all overtime request approved
    */
    private function getOverTime($empCode, $startDate, $endDate)
    {
        $overtimeRequestFormLinkName = Env::get('PAYROLL_OT_REQUEST_FORM_LINK_NAME', null);
        $response = [];
        /*
        * Pending get data by database
        */
        $overtimeRequests = $this->zohoLib->getRecords($overtimeRequestFormLinkName, 0, 200, array(
            [
                'searchField' => 'AddedBy',
                'searchOperator' => 'Contains',
                'searchText' => $empCode,
            ],
            [
                'searchField' => 'date',
                'searchOperator' => 'Between',
                'searchText' => date('Y-m-d', strtotime($startDate)) . ";" . date('Y-m-d', strtotime($endDate)),
            ],
        ));
        if (isset($overtimeRequests['errors']) && isset($overtimeRequests['errors']['code']) 
        && isset($overtimeRequests['status']) && $overtimeRequests['status'] != 0) return $response;

        if (empty($overtimeRequests)) return $response;

        foreach ($overtimeRequests as $overtime) {
            if ($overtime['ApprovalStatus'] == 'Approved') {
                $date = date('Y-m-d', strtotime($overtime['date']));
                $response[$date]['hour'] = empty($overtime['hour']) ? 0 : $overtime['hour'];
                $response[$date]['type'] = $overtime['type'];
                $response[$date]['allowance'] = empty($overtime['allowance']) ? 0 : $overtime['allowance'];
            }
        }

        return $response;
    }

    /**
    * generate tabularData to update in to monthly working time
    */
    private function processUpdateData($dataShiftConfig, $constantConfig, $employee, $leaves, $overtimes, $formEav)
    {
        $tabularAction = [];

        // All Working Time
        $paidLeave = 0;
        $holidayCount = 0;
        $standardWorkingTime = 0;
        $standardWorkingDay = 0;
        $standardWorkingDayProbation = 0;
        $otMealAllowance = 0;
        $weekdayHour = 0;
        $weekNight = 0;
        $weekendHour = 0;
        $weekendNight = 0;
        $holidayHour = 0;
        $holidayNight = 0;

        $arrayKeysDate = array_keys($dataShiftConfig);
        $dateDimension = DateDimension::whereIn('date', $arrayKeysDate)->get()->keyBy('date')->toArray();
        foreach ($dataShiftConfig as $date => $item) {
            $workingHours = 0;
            $leaveDays = 0;
            $holidayDays = 0;
            $isHoliday = false;

            $workingHours = date('H', strtotime($item['TotalHours'])) + date('i', strtotime($item['TotalHours'])) / 60;
            $workingDays = total_standard_working_day_by_working_hour($workingHours, $constantConfig);
            if (!isset($item['isWeekend']) && isset($dateDimension[$date]) && !$dateDimension[$date]['is_weekend']) {
                // ngày công tiêu chuẩn
                $standardWorkingTime++;
                if (str_contains(strtolower($item['Status']), "holiday") || str_contains(strtolower($item['Status']), "ngày lễ")) {
                    // ngày lễ
                    $holidayCount++;
                    $workingDays = 0;
                    $holidayDays = 1;
                    $isHoliday = true;
                } else {
                    if (!empty($leaves) && array_key_exists($date, $leaves)) {
                        // ngày nghỉ phép
                        $leaveDays = (float)$leaves[$date]['leave_day'];
                        $totalDays = $leaveDays + $workingDays;
                        if ($totalDays > 1) {
                            $workingDays = 1 - $leaveDays;
                        }
                        $paidLeave += $leaveDays;
                    }

                    // ngày thường
                    if (strtolower($employee['contract_type']) != 'thử việc') {
                        $standardWorkingDay += $workingDays;
                    } else {
                        //ngày thử việc
                        $standardWorkingDayProbation += $workingDays;
                    }
                }
            } else {
                // Cuối tuần
                $workingDays = 0;
                $item['FirstIn'] = '-';
                $item['LastOut'] = '-';
                //Ngày lễ chủ nhật
                if (str_contains(strtolower($item['Status']), "holiday") || str_contains(strtolower($item['Status']), "ngày lễ")) {
                    $isHoliday = true;
                }
            }

            if (!empty($overtimes)) {
                foreach ($overtimes as $otDays => $overTime) {
                    if ($otDays == $date) {
                        if (!isset($item['isWeekend']) || (date('w', strtotime($date)) != 6 && date('w', strtotime($date)) != 0)) {
                            //OT ngày thường
                            if (!$isHoliday) {
                                if ($overTime['type'] == 'Ngày') {
                                    $weekdayHour += $overTime['hour'];
                                } else {
                                    $weekNight += $overTime['hour'];
                                }
                            } else {
                                //OT Ngày Lễ
                                if ($overTime['type'] == 'Ngày') {
                                    $holidayHour += $overTime['hour'];
                                } else {
                                    $holidayNight += $overTime['hour'];
                                }
                            }
                        } else {
                            //OT cuối tuần
                            if (!$isHoliday) {
                                if ($overTime['type'] == 'Ngày') {
                                    $weekendHour += $overTime['hour'];
                                } else {
                                    $weekendNight += $overTime['hour'];
                                }
                            } else {
                                //OT Ngày Lễ + Cuối Tuần
                            }
                        }
                        $otMealAllowance += $overTime['allowance'];
                    }
                }
            }

            $sections = $formEav->sections;
            if (!$sections->isEmpty()) {
                foreach ($sections as $section) {
                    $item['append_by_logic_code_actual_Date'] = $date;
                    $item['append_by_logic_code_actual_working_day'] = $workingDays;
                    $item['append_by_logic_code_paid_leave1'] = $leaveDays;
                    $item['append_by_logic_code_holiday'] = $holidayDays;
                    $tabularRowAdd = [];
                    foreach ($section->attributes as $attribute) {
                        $tabularRowAdd[$attribute->field_label] = $this->formatFieldValueTabularByDefault($attribute->field_label, $item);
                    }
                    $tabularAction[$section->section_id]['add'][] = $tabularRowAdd;
                }
            }
        }

        return [$tabularAction, $paidLeave, $holidayCount, $standardWorkingTime, $standardWorkingDay,
         $standardWorkingDayProbation, $otMealAllowance, $weekdayHour, $weekNight, $weekendHour, $weekendNight, $holidayHour, $holidayNight];
    }
    
    /**
    * return all overtime request approved
    */
    private function formatFieldValueTabularByDefault($fieldLabel, $vars)
    {
        $value = '';
        switch ($fieldLabel) {
            case 'Date':
                $value = $vars['append_by_logic_code_actual_Date'];
                break;
            case 'Punch_in':
                $value = $vars['FirstIn'] != '-' ? date('Y-m-d H:i:s', strtotime($vars['FirstIn'])) : '';
                break;
            case 'punch_out':
                $value = $vars['LastOut'] != '-' ? date('Y-m-d H:i:s', strtotime($vars['LastOut'])) : '';
                break;
            case 'actual_working_day':
                $value = $vars['append_by_logic_code_actual_working_day'];
                break;
            case 'paid_leave1':
                $value = $vars['append_by_logic_code_paid_leave1'];
                break;
            case 'holiday':
                $value = $vars['append_by_logic_code_holiday'];
                break;
            default:
                $value = $vars[$fieldLabel] ?? '';
        }
        return $value;
    }

    /**
    * removeExistTabularZoho
    */
    private function removeExistTabularZoho(&$tabularData, $existMonthlyData, $formEav)
    {
        $sections = $formEav->sections;
        if (!$sections->isEmpty()) {
            foreach ($sections as $section) {
                $tabularExistInZoho = $existMonthlyData['tabularSections'][$section->section_name] ?? [];
                if (!empty($tabularExistInZoho[0])) {
                    foreach ($tabularExistInZoho as $value) {
                        $tabularData[$section->section_id]['delete'][] = $value['tabular.ROWID'];

                    }
                }
            }
        }
    }
}
