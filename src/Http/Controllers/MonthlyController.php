<?php

namespace Dx\Payroll\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Dx\Payroll\Helpers\getAPI;
use Dx\Payroll\Http\Controllers\ZohoController;
use Dx\Payroll\Http\Controllers\PayrollController;
use Dx\Payroll\Repositories\Eloquent\RecordsRepository;
use Dx\Payroll\Repositories\EmployeeInterface;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Dx\Payroll\Repositories\SectionsInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;

class MonthlyController extends BaseController
{
    protected $payroll, $repoSections, $zoho, $redisConfigForm, $records;

    public function __construct(PayrollController $payrollController, SectionsInterface $repoSections, ZohoController $zohoController,
        RedisConfigFormInterface $redisConfigForm, RecordsRepository $records, ZohoFormInterface $zohoForm)
    {
        $this->redisConfigForm = $redisConfigForm;
        $this->payroll = $payrollController;
        $this->repoSections = $repoSections;
        $this->zoho = $zohoController;
        $this->records = $records;
        $this->zohoForm = $zohoForm;
    }

    public function processMonthly($arrInput)
    {
        $empCode = $arrInput['code'] ?? '';
        $month = $arrInput['month'] ?? '';
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;
        $log = $empCode . ': <<< Start' . PHP_EOL;

        $config = $this->redisConfigForm->getConfig();
        if (empty($config)) {
            $log .= ' ERROR : === processWorkingTime Không có config >>>' . PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }

        // Payroll setting form
        $payrollSettings = $this->records->getRecords($arrInput['config_payroll']['setting_form_name']);
        if (empty($payrollSettings)) {
            $log .= ' ERROR : === processWorkingTime Không có PayrollConfig >>>' . PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        $arrPayrollConfig = $payrollSettings[0] ?? [];

        //Lấy các ngày trong kỳ lương
        $rangeDate = $this->payroll->rangeDate($month, $arrPayrollConfig);
        if (empty($rangeDate)) {
            $log .= 'Không tồn tại tháng cần report >>>' . PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        $fromDate = $rangeDate['from_date'];
        $toDate = $rangeDate['to_date'];

        // Lấy thông tin nhân viên
        $arrEmployee = $this->records->searchRecords('employee', $arrInput['config_payroll']['employee_code_field'], $empCode)[0];

        //Tổng ngày chấm công
        $dataPunch = $this->zoho->getAttendanceByEmployee($config['attendance']['getUserReport'], $empCode, $fromDate, $toDate);

        //Tổng ngày làm việc tiêu chuẩn trong kỳ lương
        $dataShiftConfig = $this->zoho->getShiftConfigurationByEmployee($config['attendance']['getShiftConfiguration'], $empCode, $fromDate, $toDate, $dataPunch);

        //Tổng ngày nghỉ phép
        $arrLeave = $this->payroll->getLeaveWorking($config, $empCode, $fromDate, $toDate);

        //Tổng ngày tăng ca
        $arrOT = $this->payroll->getOverTime($config[$arrInput['config_payroll']['overtime_form_name']], $empCode, $fromDate, $toDate);

        // All Working Time
        $key = 0;
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

        // Kiểm tra xem đã tồn tại monthly working report chưa
        $tabularSection = [];
        $monthlyIs = [];
        $monthlyWorkingExist = $this->zoho->searchPayroll($config[$arrInput['config_payroll']['monthly_form_name']]['getRecords'], $code);
        if (!isset($monthlyWorkingExist['errors'])) {
            $monthlyIs = $monthlyWorkingExist[0];
        }
        $listField = $this->zohoForm->getFieldOfForm($arrInput['config_payroll']['monthly_form_name']);
        foreach ($dataShiftConfig as $date => $item) {
            $workingHours = 0;
            $leaveHours = 0;
            $leaveDays = 0;
            $holidayDays = 0;
            $isHoliday = false;

            $workingHours = date('H', strtotime($item['TotalHours'])) + date('i', strtotime($item['TotalHours'])) / 60;
            $workingDays = $this->payroll->roundDay($workingHours, $arrPayrollConfig);
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
                    if (!empty($arrLeave) && array_key_exists($date, $arrLeave)) {
                        // ngày nghỉ phép
                        $leaveDays = (float)$arrLeave[$date]['leave_day'];
                        $totalDays = $leaveDays + $workingDays;
                        if ($totalDays > 1) {
                            $workingDays = 1 - $leaveDays;
                        }
                        $paidLeave += $leaveDays;
                    }
                    // ngày thường
                    if (strtolower($arrEmployee['contract_type']) != 'thử việc') {
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
            if (!empty($arrOT)) {
                foreach ($arrOT as $otDays => $overTime) {
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

            if(!$listField->isEmpty()){
                $tabularDelete = [];
                foreach ($listField as $fieldID){
                    if(!is_null($fieldID->section_id)){
                        if(!empty($monthlyIs['tabularSections'])){
                            foreach ($monthlyIs['tabularSections'] as $keyOld => $tabular){
                                if(empty($tabular)){
                                    continue;
                                }
                                foreach ($tabular as $itemOld){
                                    if(!isset($itemOld['tabular.ROWID'])){
                                        continue;
                                    }
                                    if($keyOld == $fieldID->section_name){
                                        if(!in_array($itemOld['tabular.ROWID'], $tabularDelete)){
                                            $tabularDelete[] = $itemOld['tabular.ROWID'];
                                        }
                                    }
                                }
                            }
                        }
                        $tabularSection[$fieldID->section_id]['add'][$key]['Date'] = $date;
                        $tabularSection[$fieldID->section_id]['add'][$key]['Punch_in']  = $item['FirstIn'] != '-' ? date('Y-m-d H:i:s', strtotime($item['FirstIn'])) : '';
                        $tabularSection[$fieldID->section_id]['add'][$key]['punch_out'] = $item['LastOut'] != '-' ? date('Y-m-d H:i:s', strtotime($item['LastOut'])) : '';
                        $tabularSection[$fieldID->section_id]['add'][$key]['actual_working_day'] = $workingDays;
                        $tabularSection[$fieldID->section_id]['add'][$key]['paid_leave1'] = $leaveDays;
                        $tabularSection[$fieldID->section_id]['add'][$key]['holiday'] = $holidayDays;
                        $tabularSection[$fieldID->section_id]['delete'] = $tabularDelete;
                    }
                    $tabularDelete = [];
                }
            }
            $key++;
        }
        $totalWorkingDays = $standardWorkingDay + $standardWorkingDayProbation;

        $data = [];
        $data['employee'] = $arrEmployee['Zoho_ID'];
        $data['salary_period'] = $monthly;
        $data['code'] = $code;
        $data['standard_working_time'] = $standardWorkingTime;
        $data['standard_working_day'] = $standardWorkingDay;
        $data['standard_working_day_probation'] = $standardWorkingDayProbation;
        $data['total_working_days'] = $totalWorkingDays;
        $data['holiday_count'] = $holidayCount;
        $data['paid_leave'] = $paidLeave;
        $data['total_salary_working_day'] = $totalWorkingDays + $holidayCount + $paidLeave;
        $data['ot_meal_allowance'] = $otMealAllowance;
        $data['weekday1'] = $weekdayHour;
        $data['week_night1'] = $weekNight;
        $data['weekend1'] = $weekendHour;
        $data['weekend_night1'] = $weekendNight;
        $data['holiday_hour1'] = $holidayHour;
        $data['holiday_night1'] = $holidayNight;

        if (empty($tabularSection)) {
            $log .= 'Không tồn tại tabular >>>' . PHP_EOL;
            $this->logDebug(true, $module, $log);
            return 1;
        }

        if (!empty($monthlyIs)) {
            $zohoId = $monthlyIs['Zoho_ID'] ?? '';
            $actionPay = $config[$arrInput['config_payroll']['monthly_form_name']]['updateRecord'];
        } else {
            $zohoId = '';
            $actionPay = $config[$arrInput['config_payroll']['monthly_form_name']]['insertRecord'];
        }

        // Insert if not found
        if (empty($zohoId)) {
            $insertMonthly = $this->zoho->createdOrUpdated($actionPay, $data, [], $zohoId, 'yyyy-MM-dd');
            if (isset($insertMonthly['result']['pkId'])) {
                $log .= 'Create monthly success' . PHP_EOL;
                $zohoId = $insertMonthly['result']['pkId'] ?? '';
                $actionPay = $config[$arrInput['config_payroll']['monthly_form_name']]['updateRecord'];
            } else {
                $log .= 'Create monthly failed' . PHP_EOL;
                Log::channel('dx')->info($log);
                return 0;
            }
        }

        if (!empty($zohoId)) {
            $response = $this->zoho->createdOrUpdated($actionPay, $data, $tabularSection, $zohoId, 'yyyy-MM-dd');
            if (isset($response['result']['pkId'])) {
                $log .= 'Update Success.' . PHP_EOL;
            } else {
                $log .= 'Update Failed' . PHP_EOL;
            }
        }
        Log::channel('dx')->info($log);
        return 200;
    }

}
