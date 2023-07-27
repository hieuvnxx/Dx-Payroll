<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Http\Controllers\EmployeeController;
use Dx\Payroll\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Dx\Payroll\Repositories\Contracts\RedisRepository;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Dx\Payroll\Repositories\Eloquent\RecordsRepository;
use Dx\Payroll\Repositories\Eloquent\RedisConfigFormRepository;
use Dx\Payroll\Jobs\MonthlyJob;
use Dx\Payroll\Jobs\PayslipJob;

class PayrollController extends BaseController
{

    protected $redisConfigForm, $zoho, $records;

    public function __construct(ZohoController $zohoController, RedisConfigFormInterface $redisConfigForm,
        RedisConfigFormRepository $redisControl, RecordsRepository $records)
    {
        $this->zoho = $zohoController;
        $this->redisConfigForm = $redisConfigForm;
        $this->redisControl = $redisControl;
        $this->records = $records;
        $this->configPayroll = [
            //employee
            "employee_form_name" => "employee",
            "offer_section" => "Salary History",
            "employee_code_field" => "EmployeeID",
            //monthly
            "monthly_form_name" => "monthly_working_time",
            "monthly_section" => "Attendance details/Bảng công chi tiết",
            "monthly_emp_code_field" => "employee",
            "monthly_period_field" => "salary_period",
            //payslip
            "payslip_form_name" => "payslip1",
            "payslip_emp_code_field" => "employee1",
            "payslip_period_field" => "salary_period",
            //package form
            "overtime_form_name" => "ot_request",
            "setting_form_name" => "setting",
            "form_master_form_name" => "form_master_data",
            "factor_master_form_name" => "factor_master_data",
            "fomular_form_name" => "fomular",
        ];
    }

    /**
     * Get config form from database
     *
     * @param $token
     * @return array|mixed
     */

    public function payrollProcess(Request $request)
    {
        $config = $this->redisConfigForm->getConfig();
        if (empty($config)) {
            return $this->sendError('Empty config', [], 404);
        }

        if (!isset($request->module) || !in_array($request->module, ['dx_monthly', 'dx_payslip']) ||
            !isset($request->type) || !in_array($request->type, ['one', 'all'])) {
            Log::channel('dx')->info('Missing Request: ' . json_encode($request->post()));
            return $this->sendError('Missing or Invalid required parameters(module, type)', [], 400);
        }

        if (!isset($request->month)) {
            $request->merge(['month' => date('m-Y')]);
        } else {
            $request->merge(['month' => str_replace('/', '-', $request->month)]);
        }


        if ($request->type == 'one') {
            if (empty($request->employee)) {
                Log::channel('dx')->info('Missing Employee ID');
                return $this->sendError('Missing Employee ID', [], 400);
            }
            $empItem = $this->explodePattern($request->employee, '-');
            $request->merge(['code' => $empItem['code']]);
        }

        if (!empty($request->code)) {
            $arrEmp = $this->records->searchRecords('employee', 'EmployeeID', $request->code);
        } else {
            $arrEmp = $this->records->getRecords('employee');
        }
        if (empty($arrEmp)) {
            return $this->sendError('Not found employee', [], 404);
        }


        $count = 0;
        $delayTime = 0;
        foreach ($arrEmp as $empInfo) {
            if ($empInfo['Employeestatus'] != "Active" || $empInfo['payroll_eligibility'] == "No" || !isset($empInfo['TabularSections'][$this->configPayroll['offer_section']])) {
                Log::channel('dx')->info($empInfo['EmployeeID'] . ': Nhân viên không có offer hoặc không được tính lương >>>' . PHP_EOL);
            } else {
                $request->merge(['code' => $empInfo['EmployeeID']]);
                $request->merge(['config_payroll' => $this->configPayroll]);
                if ($request->module == 'dx_monthly') {
                    MonthlyJob::dispatch(base64_encode(json_encode($request->post())))->onQueue($request->module)->delay($delayTime);
                } else {
                    PayslipJob::dispatch(base64_encode(json_encode($request->post())))->onQueue($request->module)->delay($delayTime);
                }
                $count++;
                if ($count == 10) {
                    $count = 0;
                    $delayTime = $delayTime + 5;
                }

            }
        }
        return $this->sendResponse('Success', 'End payrollProcess!');
    }

    public function explodePattern($txt = '', $gexp = '-', $num = 1)
    {
        $response['code'] = '';
        $response['title'] = '';
        if ($gexp == '') {
            $gexp = '-';
        }
        if ($txt != '') {
            $extEmp = explode($gexp, $txt);
            if (is_numeric(trim($extEmp[0]))) {
                $code = trim($extEmp[0]);
                $title = trim($extEmp[1]);
            } else {
                $code = trim($extEmp[1]);
                $title = trim($extEmp[0]);
            }
            $response['code'] = $code;
            $response['title'] = $title;
        }
        return $response;
    }

    public function rangeDate($monthly = '', $arrPayrollConfig = [])
    {
        $response = [];
        if (!empty($arrPayrollConfig) && !empty($monthly)) {
            $fromSalary = $arrPayrollConfig['from_date'] ?? '21';
            $toSalary = $arrPayrollConfig['to_date'] ?? '20';
            // Kiểm tra có cùng tháng hay không
            ($fromSalary > $toSalary) ? $num = 1 : $num = 0;
            $response['from_date'] = date('Y-m-d', strtotime($fromSalary . '-' . $monthly . " -" . $num . " months"));
            $response['to_date'] = date('Y-m-d', strtotime($toSalary . '-' . $monthly));
        }
        return $response;
    }

    public function getLeaveWorking($config = [], $empCode = '', $startDate = '', $endDate = '')
    {
        $arrResponse = [];
        if (!empty($empCode)) {
            $arrData = $this->zoho->searchLeaveWorking($config['leave']['getRecords'], $empCode, $startDate, $endDate, true);
            if (isset($arrData['status']) && ($arrData['status'] == -1 || $arrData['status'] == 1)) {
                return [];
            }
            if (!empty($arrData)) {
                foreach ($arrData as $key => $item) {
                    if (str_contains(strtolower($item['Leavetype']), "unpaid")) {
                        continue;
                    }
                    $arrDetailLeave = $this->zoho->getRecordByID($item['Zoho_ID'], $config['leave']['getRecordByID']);
                    sleep(3);
                    if (isset($arrDetailLeave['status']) && $arrDetailLeave['status'] == 1) {
                        return [];
                    }
                    if ($arrDetailLeave['ApprovalStatus'] == 'Approved') {
                        if (!empty($arrDetailLeave['DayDetails'])) {
                            foreach ($arrDetailLeave['DayDetails'] as $day => $val) {
                                if ($val['LeaveCount'] == '0.0') continue;
                                $day = date('Y-m-d', strtotime($day));
                                if (strtotime($day) >= strtotime($startDate) && strtotime($day) <= strtotime($endDate)) {
                                    if (isset($arrResponse[$day]['leave_day'])) {
                                        $arrResponse[$day]['leave_day'] += $val['LeaveCount'];
                                    } else {
                                        $arrResponse[$day]['leave_day'] = $val['LeaveCount'];
                                        $arrResponse[$day]['leave_type'] = $item['Leavetype'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $arrResponse;
    }

    public function getOverTime($form = [], $empCode = '', $startDate = '', $endDate = '')
    {
        $response = [];
        if (!empty($empCode)) {
            $arrData = $this->zoho->getOvertimeByEmployee($form['getRecords'], $empCode, $startDate, $endDate);
            if (!isset($arrData['status']) && !empty($arrData)) {
                foreach ($arrData as $overtime) {
                    if ($overtime['ApprovalStatus'] != 'Approved') {
                        $date = date('Y-m-d', strtotime($overtime['date']));
                        $response[$date]['hour'] = empty($overtime['hour']) ? 0 : $overtime['hour'];
                        $response[$date]['type'] = $overtime['type'];
                        $response[$date]['allowance'] = empty($overtime['allowance']) ? 0 : $overtime['allowance'];
                    }
                }
            }
        }
        return $response;
    }

    /**
     * @param array $config
     * @param int $totalHour
     * @param int $arrLeave
     * @return array
     */

    public function convertWorkingDayLeave($config = [], $arrPayrollConfig = [], $totalHour = 0, $arrLeave = 0)
    {
        $response['actual_hour'] = 0;
        $response['actual_day'] = 0;
        $response['salary_day'] = 0;
        $response['annual_leave'] = 0;
        $response['other_paid_leaves'] = 0;
        $response['seniority_leave'] = 0;
        if (isset($arrLeave['leave_type'])) {
            $leaveDay = $arrLeave['leave_day'];
            $leaveDayApproval = $leaveDay;
            $totalDaySalary = $this->roundDay($totalHour, $arrPayrollConfig) + $leaveDay;
            //Nghỉ thai sản
            if (strpos(strtolower($arrLeave['leave_type']), 'maternity leave') !== false) {
                $response['maternity_leave'] = $leaveDayApproval;
                if ($totalHour > 8) {
                    $totalHour = 8;
                }
                $response['actual_hour'] = round($totalHour, 2);
                $response['actual_day'] = $this->roundDay($totalHour, $arrPayrollConfig);
                $response['salary_day'] = $this->roundDay($totalHour, $arrPayrollConfig);
            } else {
                if ($totalDaySalary > 1) {
                    $salaryDay = 1;
                    $actualDay = (1 - $leaveDay) * 8;
                } else {
                    $salaryDay = $totalDaySalary;
                    $actualDay = $totalHour;
                }
                $response['actual_hour'] = round($actualDay, 2);
                $response['actual_day'] = $this->roundDay($actualDay, $arrPayrollConfig);
                $response['salary_day'] = $salaryDay;
                //Nghỉ cưới, tang
                if (strpos(strtolower($arrLeave['leave_type']), 'marriage leave') !== false || strpos(strtolower($arrLeave['leave_type']), "bereavement leave") !== false) {
                    $response['other_paid_leaves'] = $leaveDayApproval;
                }
                if (strpos(strtolower($arrLeave['leave_type']), 'annual leave') !== false) {
                    $response['annual_leave'] = $leaveDayApproval;
                }
                if (strpos(strtolower($arrLeave['leave_type']), 'seniority leave') !== false) {
                    $response['seniority_leave'] = $leaveDayApproval;
                }
                if (strpos(strtolower($arrLeave['leave_type']), 'after 5 years') !== false || strpos(strtolower($arrLeave['leave_type']), 'after 5ys') !== false) {
                    $response['annual_leave'] = $leaveDayApproval;
                }
            }
        }
        return $response;
    }

    public function roundDay($totalHour = 0, $arrPayrollConfig = [])
    {
        if ($totalHour == '') {
            $totalHour = 0;
        }
        $halfDay = (double)$arrPayrollConfig['standard_working_hour_haftday'];
        $fullDay = (double)$arrPayrollConfig['standard_working_hour'];
        if ($totalHour >= $fullDay) {
            $day = 1;
        } elseif ($totalHour >= $halfDay) {
            $day = 0.5;
        } else {
            $day = 0;
        }
        return $day;
    }
}
