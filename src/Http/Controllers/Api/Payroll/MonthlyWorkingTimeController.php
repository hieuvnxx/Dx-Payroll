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
use Illuminate\Support\Facades\DB;

/**
 * insert database zoho form
 */
class MonthlyWorkingTimeController extends PayrollController
{
    protected $dxPayrollConfig;
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
        $this->dxPayrollConfig = config('dx_payroll');
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
            $employees = $this->zohoRecord->getRecords($employeeFormLinkName, $offset, $limit, [
                'Employeestatus' => [
                    'searchText' => "Active",
                    'searchOperator' => 'Is'
                ],
            ]);

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
        $monthlyPayrollModuleConfigDynamic = $this->dxPayrollConfig['monthly_working_time'];
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
        $employee = $this->zohoRecord->getRecords($employeeFormLinkName, 0, 1, [
            $employeeIdNumberFieldLabel => [
                'searchText' => $empCode,
                'searchOperator' => 'Is'
            ]
        ])[0];

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
        $overtimes = $this->getOverTime($empCode, $fromSalary, $toSalary);

        // check exist record monthly
        $existMonthlyIds = [];
        $monthlyWorkingTimeExistZoho = $this->zohoRecord->getRecords($monthlyWorkingTimeFormLinkName, 0, 200, [
            'employee' => [
                'searchText' => $empCode,
                'searchOperator' => 'Contains'
            ],
            'salary_period' => 
            [
                'searchText' => $monthly,
                'searchOperator' => 'Is',
            ]
        ]);

        $monthlyDataCollect = $this->processUpdateData($dataShiftConfig, $constantConfig, $employee, $leaves, $overtimes, $formEav);
        
        $paidLeave = $monthlyDataCollect['paid_leave'];
        $holidayCount = $monthlyDataCollect['holiday_count'];
        $standardWorkingDay = $monthlyDataCollect['standard_working_day'];
        $standardWorkingDayProbation = $monthlyDataCollect['standard_working_day_probation'];
        $totalWorkingDays = sum_number($standardWorkingDay, $standardWorkingDayProbation);
        $totalSalaryWorkingDay = sum_number($totalWorkingDays, $holidayCount, $paidLeave);

        $inputData = [
            $monthlyPayrollModuleConfigDynamic['code']                                  => $code,
            $monthlyPayrollModuleConfigDynamic['employee']                              => $employee['Zoho_ID'],
            $monthlyPayrollModuleConfigDynamic['salary_period']                         => $monthly,
            $monthlyPayrollModuleConfigDynamic['standard_working_time']                 => $monthlyDataCollect['standard_working_time'],
            $monthlyPayrollModuleConfigDynamic['overtime_meal_allowance']               => $monthlyDataCollect['meal_allowance'],
            $monthlyPayrollModuleConfigDynamic['total_working_days']                    => $totalWorkingDays,
            $monthlyPayrollModuleConfigDynamic['standard_working_day_probation']        => $standardWorkingDayProbation,
            $monthlyPayrollModuleConfigDynamic['standard_working_day']                  => $standardWorkingDay,
            $monthlyPayrollModuleConfigDynamic['holidays']                              => $holidayCount,
            $monthlyPayrollModuleConfigDynamic['paid_leave']                            => $paidLeave,
            $monthlyPayrollModuleConfigDynamic['total_salary_working_day']              => $totalSalaryWorkingDay,
            $monthlyPayrollModuleConfigDynamic['weekday_hour']                          => $monthlyDataCollect['week_day_hour'],
            $monthlyPayrollModuleConfigDynamic['weekend_hour']                          => $monthlyDataCollect['weekend_day_hour'],
            $monthlyPayrollModuleConfigDynamic['holiday_hour']                          => $monthlyDataCollect['holiday_day_hour'],
            $monthlyPayrollModuleConfigDynamic['weekday_night_hour']                    => $monthlyDataCollect['week_night_hour'],
            $monthlyPayrollModuleConfigDynamic['weekend_night_hour']                    => $monthlyDataCollect['weekend_night_hour'],
            $monthlyPayrollModuleConfigDynamic['holiday_night_hour']                    => $monthlyDataCollect['holiday_night_hour'],
        ];

        $tabularData = $monthlyDataCollect['tabular_data'];

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
    public function getLeaveWorking($empCode, $startDate, $endDate)
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
    public function getOverTime($empCode, $startDate, $endDate)
    {
        $overtimeRequestFormLinkName = Env::get('PAYROLL_OT_REQUEST_FORM_LINK_NAME', null);
        $response = [];

        $overtimeRequests = $this->zohoRecord->getRecords($overtimeRequestFormLinkName, 0, 200, [
            'employee1' => [
                'searchText' => $empCode,
                'searchOperator' => 'Contains'
            ],
            'date' => 
            [
                'searchText' => [date('Y-m-d', strtotime($startDate)), date('Y-m-d', strtotime($endDate))],
                'searchOperator' => 'Between',
            ]
        ]);

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
    public function processUpdateData($dataShiftConfig, $constantConfig, $employee, $leaves, $overtimes, $formEav)
    {
        $tabularAction = [];

        // All Working Time
        $paidLeave = $holidayCount = $standardWorkingTime = $standardWorkingDay = $standardWorkingDayProbation = 0;

        // OT Section
        $otMealAllowance = $weekdayHour = $weekNight = $weekendHour = $weekendNight = $holidayHour = $holidayNight = $holidayWithWeekendHour = $holidayWithWeekendNight = 0;

        $constantShiftByEmployee = $this->getConstantShiftConfig($constantConfig, $employee[$this->dxPayrollConfig['employee']['shift']]);

        $beginningDate = $employee[$this->dxPayrollConfig['employee']['beginning_date']] ?
                        Carbon::createFromFormat('d-F-Y', $employee[$this->dxPayrollConfig['employee']['beginning_date']])->format('Y-m-d') : null;

        if ($beginningDate) $beginningDate = Carbon::createFromFormat('Y-m-d', $beginningDate);

        $arrayKeysDate = array_keys($dataShiftConfig);
        $dateDimension = DateDimension::whereIn('date', $arrayKeysDate)->get()->keyBy('date');
        foreach ($dataShiftConfig as $date => $shiftConfig) {
            $workingHours = $leaveDays = $holidayDays = 0;

            $isHoliday = $isWeekDay = $isWeekend = $isLongtermLeave = false;
            $workingHours = date('H', strtotime($shiftConfig['TotalHours'])) + date('i', strtotime($shiftConfig['TotalHours'])) / 60;
            $workingDays = total_standard_working_day_by_working_hour($workingHours, $constantShiftByEmployee['standard_working_hour'], $constantShiftByEmployee['standard_working_hour_halfday']);

            if (str_contains(strtolower($shiftConfig['Status']), "holiday") || str_contains(strtolower($shiftConfig['Status']), "ngày lễ")) {
                $isHoliday = true;
                $workingDays = 0;
            }

            if (str_contains(strtolower($shiftConfig['Status']), "maternity") || str_contains(strtolower($shiftConfig['Status']), "thai sản")) {
                $isLongtermLeave = true;
                $workingDays = $leaveDays = 0;
            }
            
            //OT Ngày Lễ
            if (!empty($overtimes[$date])) {
                $otMealAllowance += $overtimes[$date]['allowance'];
            }

            if ($dateDimension->has($date) && !isset($shiftConfig['isWeekend']) && !$dateDimension[$date]->is_weekend) {
                $isWeekDay = true;
            } else {
                $isWeekend = true;
            }

            if ($isWeekDay) {
                $standardWorkingTime++;
                if ($isHoliday) {
                    $holidayCount++;
                    $holidayDays = 1;

                    //OT Ngày Lễ
                    if (!empty($overtimes[$date])) {
                        if ($overtimes[$date]['type'] == 'Ngày') {
                            $holidayHour += $overtimes[$date]['hour'];
                        } else {
                            $holidayNight += $overtimes[$date]['hour'];
                        }
                    }
                } else {
                    if(!$isLongtermLeave && !empty($leaves[$date])) {
                        $leaveDays = (float)$leaves[$date]['leave_day'];
                        $totalDays = $leaveDays + $workingDays;
                        if ($totalDays > 1) {
                            $workingDays = 1 - $leaveDays;
                        }
                        $paidLeave += $leaveDays;
                    }

                    $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
                    // ngày thường
                    if (strtolower($employee['contract_type']) != 'thử việc' && $beginningDate && $carbonDate->gte($beginningDate)) {
                        $standardWorkingDay += $workingDays;
                    } else {
                        //ngày thử việc
                        $standardWorkingDayProbation += $workingDays;
                    }
                    
                    //OT ngày thường
                    if (!empty($overtimes[$date])) {
                        if ($overtimes[$date]['type'] == 'Ngày') {
                            $weekdayHour += $overtimes[$date]['hour'];
                        } else {
                            $weekNight += $overtimes[$date]['hour'];
                        }
                    }
                }
            }

            if ($isWeekend && !empty($overtimes[$date])) {
                if ($isHoliday) {
                    //OT Ngày Lễ + Cuối Tuần
                    if ($overtimes[$date]['type'] == 'Ngày') {
                        $holidayWithWeekendHour += $overtimes[$date]['hour'];
                    } else {
                        $holidayWithWeekendNight += $overtimes[$date]['hour'];
                    }
                    
                } else {
                    //OT cuối tuần
                    if ($overtimes[$date]['type'] == 'Ngày') {
                        $weekendHour += $overtimes[$date]['hour'];
                    } else {
                        $weekendNight += $overtimes[$date]['hour'];
                    }
                }
            }

            $sections = $formEav->sections;
            if (!$sections->isEmpty()) {
                foreach ($sections as $section) {
                    $shiftConfig['append_by_logic_code_actual_Date'] = $date;
                    $shiftConfig['append_by_logic_code_actual_working_day'] = $workingDays;
                    $shiftConfig['append_by_logic_code_paid_leave1'] = $leaveDays;
                    $shiftConfig['append_by_logic_code_holiday'] = $holidayDays;
                    $tabularRowAdd = [];
                    foreach ($section->attributes as $attribute) {
                        $tabularRowAdd[$attribute->label_name] = $this->formatFieldValueTabularByDefault($attribute->label_name, $shiftConfig);
                    }
                    $tabularAction[$section->section_id]['add'][] = $tabularRowAdd;
                }
            }
        }

        return [
            'paid_leave' => $paidLeave,
            'holiday_count' => $holidayCount,
            'standard_working_time' => $standardWorkingTime,
            'standard_working_day' => $standardWorkingDay,
            'standard_working_day_probation' => $standardWorkingDayProbation,
            'meal_allowance' => $otMealAllowance,
            'week_day_hour' => $weekdayHour,
            'week_night_hour' => $weekNight,
            'weekend_day_hour' => $weekendHour,
            'weekend_night_hour' => $weekendNight,
            'holiday_day_hour' => $holidayHour,
            'holiday_night_hour' => $holidayNight,
            'holiday_with_weekend_day_hour' => $holidayWithWeekendHour,
            'holiday_with_weekend_night_hour' => $holidayWithWeekendNight,

            'tabular_data' => $tabularAction,
        ];
    }
    
    /**
    * return all overtime request approved
    */
    public function formatFieldValueTabularByDefault($fieldLabel, $vars)
    {
        $value = '';
        switch ($fieldLabel) {
            case 'Date':
                $value = $vars['append_by_logic_code_actual_Date'];
                break;
            case 'Punch_in':
                $value = ($vars['FirstIn'] != '-' && $vars['FirstIn']) ? date('Y-m-d H:i:s', strtotime($vars['FirstIn'])) : '';
                break;
            case 'punch_out':
                $value = ($vars['LastOut'] != '-' && $vars['LastOut']) ? date('Y-m-d H:i:s', strtotime($vars['LastOut'])) : '';
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
    public function removeExistTabularZoho(&$tabularData, $existMonthlyData, $formEav)
    {
        $sections = $formEav->sections;
        if (!$sections->isEmpty()) {
            foreach ($sections as $section) {
                $tabularExistInZoho = $existMonthlyData['tabularSections'][$section->section_name] ?? [];
                if (!empty($tabularExistInZoho)) {
                    foreach ($tabularExistInZoho as $key => $value) {
                        $tabularData[$section->section_id]['delete'][] = strval($key);
                    }
                }
            }
        }
    }

    public function getConstantShiftConfig($constantConfig, $employeeType)
    {
        $tabularLabel = $this->dxPayrollConfig['constant_configuration']['tabularSections']['shift'];
        $tabularName = $this->dxPayrollConfig['constant_configuration']['tabularNameSections']['shift'];
        $tabularShiftConstantConfig = $constantConfig['tabularSections'][$tabularName];

        foreach ($tabularShiftConstantConfig as $shift) {
            if ($shift[$tabularLabel['shift_name']] == $employeeType) {
                return [
                    'shift' => $shift[$tabularLabel['shift_name']],
                    'from_time' => $shift[$tabularLabel['from_time']],
                    'to_time' => $shift[$tabularLabel['to_time']],
                    'standard_working_hour' => $shift[$tabularLabel['standard_working_hour']],
                    'standard_working_hour_halfday' => $shift[$tabularLabel['standard_working_hour_halfday']],
                ];
            }
        }

        return [];
    }
}
