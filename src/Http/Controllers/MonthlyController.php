<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Helpers\getAPI;
use Dx\Payroll\Http\Controllers\PayrollController;
use Dx\Payroll\Repositories\EmployeeInterface;
use Dx\Payroll\Http\Controllers\ZohoController;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Illuminate\Support\Facades\Log;

class MonthlyController extends BaseController
{
    protected $payroll, $repoEmployee, $zoho, $redisConfigForm;

    public function __construct(PayrollController $payrollController, EmployeeInterface $repoEmployee, ZohoController $zohoController, RedisConfigFormInterface $redisConfigForm)
    {
        $this->redisConfigForm = $redisConfigForm;
        $this->payroll = $payrollController;
        $this->repoEmployee = $repoEmployee;
        $this->zoho = $zohoController;
    }

    public function processMonthly($arrInput){
        $monthlyForm = 'monthy_worktime_report';
        $overTimeForm = 'Overtime_Registration';
        $log        = '';
        $empCode    = $arrInput['code'] ?? '';
        $month      = $arrInput['month'] ?? '';
        $zohoId     = $arrInput['zoho_id'] ?? '';

        $config = $this->redisConfigForm->getConfig();
        if(empty($config)){
            $log .= ' ERROR : === processWorkingTime Không có config >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        $payrollSettings = $this->payroll->getPayrollConfig();
        if(isset($payrollSettings['code']) && $payrollSettings['code'] == 1 || empty($payrollSettings['data'])){
            $log .= ' ERROR : === processWorkingTime Không có PayrollConfig >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        $arrPayrollConfig = $payrollSettings['data'] ?? [];

        $log .= $empCode.': <<< BEGIN processWorkingTime'.PHP_EOL;
        $arrEmployee = $this->repoEmployee->getEmployeeByCode($empCode);

        $rangeDate = $this->payroll->rangeSalaryDate($month, $arrPayrollConfig);
        if(empty($rangeDate)){
            $log .= 'Không tồn tại tháng cần report >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        $fromDate = date("Y-m-d", strtotime(reset($rangeDate)));
        $toDate = date("Y-m-d", strtotime(end($rangeDate)));

        $dataOfferSalary = $this->payroll->diffOfferSalary($arrEmployee->offerSalary, $fromDate, $toDate);
        if(empty($dataOfferSalary)){
            $log .= 'Không có Offer Salary >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }

        //Tổng ngày chấm công
        $dataPunch = $this->zoho->getAttendanceByEmployee($config['attendance']['getUserReport'], $empCode, $fromDate, $toDate);
        ksort($dataPunch);
        //Tổng ngày làm việc tiêu chuẩn trong kỳ lương
        $dataShiftConfig = $this->zoho->getShiftConfigurationByEmployee($config['attendance']['getShiftConfiguration'], $empCode, $fromDate, $toDate);
        $shiftConfiguration = $dataShiftConfig['data'] ?? [];
        //Tổng ngày nghỉ phép
        $arrLeave = $this->payroll->getLeaveWorking($config, $empCode, $fromDate, $toDate);

        $arrWorking = $this->payroll->workingBreakTime($overTimeForm, $config, $arrPayrollConfig, $dataPunch, $shiftConfiguration, $arrLeave, $empCode, $dataOfferSalary);

        $tabularSection = [];
        $expenseTabularSection  = $this->zoho->getSectionForm($config[$monthlyForm]['components'], 2);
        $tabularSectionId       = $expenseTabularSection[$config[$monthlyForm]['working_salary_detail_key']]['sectionId'] ?? '';
        if(!empty($arrWorking)) {
            $key = 0;
            foreach ($dataOfferSalary as $offer)
            {
                foreach ($arrWorking as $day => $item)
                {
                    if($item['punch_in'] == '-' || $item['punch_in'] == ''){
                        $firstIn = '';
                    }else{
                        $firstIn = date('Y-m-d H:i:s', strtotime($item['punch_in']));
                    }
                    if($item['punch_out'] == '-' || $item['punch_out'] == ''){
                        $lastOut = '';
                    }else{
                        $lastOut = date('Y-m-d H:i:s', strtotime($item['punch_out']));
                    }

                    if($firstIn){
                        $tabularSection[$tabularSectionId]['add'][$key]['Punch_in'] = $firstIn;
                    }
                    if($lastOut){
                        $tabularSection[$tabularSectionId]['add'][$key]['punch_out'] = $lastOut;
                    }
                    $tabularSection[$tabularSectionId]['add'][$key]['holiday']              = $item['holiday'];
                    $tabularSection[$tabularSectionId]['add'][$key]['paid_leave1']          = $item['annual_leave'] + $item['seniority_leave'] + $item['other_paid_leaves'];
                    $tabularSection[$tabularSectionId]['add'][$key]['actual_working_day']   = $item['actual_day'];

                    // Kiểm tra nếu ngày tính lương sau ngày chấm công
                    $fromDateSalary   = array_key_first($dataOfferSalary);
                    if($fromDateSalary > $day){
                        $tabularSection[$tabularSectionId]['add'][$key]['Punch_in'] = '';
                        $tabularSection[$tabularSectionId]['add'][$key]['punch_out'] = '';
                        $tabularSection[$tabularSectionId]['add'][$key]['holiday']              = 0;
                        $tabularSection[$tabularSectionId]['add'][$key]['paid_leave1']          = 0;
                        $tabularSection[$tabularSectionId]['add'][$key]['actual_working_day']   = 0;
                    }
                    if(!empty($endDateOfferSalary) && $endDateOfferSalary < $day){
                        $tabularSection[$tabularSectionId]['add'][$key]['Punch_in'] = '';
                        $tabularSection[$tabularSectionId]['add'][$key]['punch_out'] = '';
                        $tabularSection[$tabularSectionId]['add'][$key]['actual_working_day'] = 0;
                        $tabularSection[$tabularSectionId]['add'][$key]['total_salary_day']     = 0;
                        $item['actual_day'] = 0;
                        $item['salary_day'] = 0;
                    }

                    if($item['holiday'] > 0){
                        $tabularSection[$tabularSectionId]['add'][$key]['actual_working_day']                 = 0;
                        //Tổng thời gian thực tế đi làm ngày lễ luôn = 0
                        $totalActualDay += 0;
                    }else{
                        $tabularSection[$tabularSectionId]['add'][$key]['actual_working_day']   = $item['actual_day'];

                        //Tổng thời gian thực tế đi làm
                        $totalActualDay += $item['actual_day'];
                    }
                    $tabularSection[$tabularSectionId]['add'][$key]['Date']                 = $day;


                    //Tổng ngày được tính lương và các ngày phép
                    $totalSalaryDay += $item['salary_day'];
                    // Tổng ngày leave
                    $totalPaidLeave += $item['annual_leave'] + $item['seniority_leave'] + $item['other_paid_leaves'];
                    // Tổng ngày lễ
                    $totalHoliday += $item['holiday'];

                    //Tổng overtime weekday
                    $overtimeWeekday += $item['overtime_weekday'];
                    $overtimeWeekend += $item['overtime_weekend'];
                    $overtimeHoliday += $item['overtime_holiday'];
                    $totalMeal       += $item['total_meal'];
                    $key++;
                }
            }
        }
        dd($tabularSection);
    }
}
