<?php

namespace Dx\Payroll\Jobs\Payroll;

use Carbon\Carbon;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;

class PushMonthyWorkingTimePerEmployeeToZoho implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $employee;
    private $month;

    private $zohoLib;
    private $zohoForm;
    private $zohoRecord;
    private $zohoRecordValue;

    /**
     * Create a new job instance.
     * the dataJob sample :
     * [
     *      'employee' => [...]
     *      'month' => '05-2023'
     * ]
     * @return void
     */
    public function __construct($dataJob)
    {
        $this->employee = $dataJob['employee'];
        $this->month = $dataJob['month'];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord, ZohoRecordValueInterface $zohoRecordValue)
    {
        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $this->zohoForm = $zohoForm;
        $this->zohoRecord = $zohoRecord;
        $this->zohoRecordValue = $zohoRecordValue;

        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');
        $constantConfigFormLinkName = Env::get('PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME');
        $monthlyWorkingTimeFormLinkName = Env::get('PAYROLL_MONTHY_WORKING_TIME_FORM_LINK_NAME');

        $employee = $this->employee;
        $month    = $this->month;

        $empCode = $employee[$employeeIdNumberFieldLabel];
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;

        $formEav = $this->zohoForm->has('attributes', 'sections', 'sections.attributes')->with('attributes', 'sections', 'sections.attributes')->where('form_link_name', $monthlyWorkingTimeFormLinkName)->first();
        if (is_null($formEav)) {
            Log::channel('dx')->info('formEav is null ::: empCode :::' . $empCode);
            throw new \ErrorException('formEav is null');
        }

        $constantConfigs = $this->zohoRecord->getRecords($constantConfigFormLinkName);
        if (empty($constantConfigs)) {
            Log::channel('dx')->info('Constant configuration empty in database. Please re-generate constant configuration.' . $empCode);
            throw new \ErrorException('Constant configuration empty in database. Please re-generate constant configuration.');
        }

        $constantConfig = $constantConfigs[0];

        list($fromSalary, $toSalary) = payroll_range_date($month, $constantConfig['from_date'], $constantConfig['to_date']);

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
                'searchField' => 'code',
                'searchOperator' => 'Is',
                'searchText' => $code,
            ],
            [
                'searchField' => 'salary_period',
                'searchOperator' => 'Is',
                'searchText' => $monthly,
            ],
        ));
        
        /* remove exist record in zoho if exist */
        if (!empty($monthlyWorkingTimeExistZoho[0])) {
            $existMonthlyIds = collect($monthlyWorkingTimeExistZoho)->map(function ($data) {
                return $data['Zoho_ID'];
            });
            $existMonthlyStringIds = implode(',', $existMonthlyIds->toArray());
            $monthlyWorkingTimeExistZoho = $this->zohoLib->deleteRecords($monthlyWorkingTimeFormLinkName, $existMonthlyStringIds);
        }

        list($tabularData, $paidLeave, $holidayCount,
        $standardWorkingTime, $standardWorkingDay, $standardWorkingDayProbation,
        $otMealAllowance, $weekdayHour, $weekNight, $weekendHour,
        $weekendNight, $holidayHour, $holidayNight) = $this->processUpdateData($dataShiftConfig, $constantConfig, $employee, $leaves, $overtimes, $formEav);

        if (empty($tabularData)) {
            Log::channel('dx')->info('Something error. Can not generate attendance detail. empCode:' . $empCode);
            throw new \ErrorException('Something error. Can not generate attendance detail. empCode: ' . $empCode);
        }
        
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

        $rspInsert = $this->zohoLib->insertRecord($monthlyWorkingTimeFormLinkName, $inputData, 'yyyy-MM-dd');
        if (!isset($rspInsert['result']) || !isset($rspInsert['result']['pkId'])) {
            Log::channel('dx')->info('Something error. Can not insert new record monthy working time in to zoho. empCode:' . $empCode);
            throw new \ErrorException('Something error. Can not insert new record monthy working time in to zoho.');
        }

        $zohoId = $rspInsert['result']['pkId'];

        $rspUpdate = $this->zohoLib->updateRecord($monthlyWorkingTimeFormLinkName, $inputData, $tabularData, $zohoId, 'yyyy-MM-dd');
        if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
            Log::channel('dx')->info('Something error. Can not update attendance detail to record monthy working time with id :' . $zohoId);
            throw new \ErrorException('Something error. Can not update attendance detail to record monthy working time with id : '. $zohoId);
        }
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

        foreach ($dataShiftConfig as $date => $item) {
            $workingHours = 0;
            $leaveHours = 0;
            $leaveDays = 0;
            $holidayDays = 0;
            $isHoliday = false;

            $workingHours = date('H', strtotime($item['TotalHours'])) + date('i', strtotime($item['TotalHours'])) / 60;
            $workingDays = total_standard_working_day_by_working_hour($workingHours, $constantConfig);
            if (!isset($item['isWeekend']) || (date('w', strtotime($date)) != 6 && date('w', strtotime($date)) != 0)) {
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
}
