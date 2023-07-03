<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Repositories\Contracts\RedisRepository;
use Dx\Payroll\Repositories\EmployeeInterface;
use Dx\Payroll\Repositories\PayrollSettingsInterface;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Dx\Payroll\Repositories\Eloquent\RedisConfigFormRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Dx\Payroll\Http\Controllers\EmployeeController;
use Dx\Payroll\Http\Controllers\BaseController;
use Dx\Payroll\Jobs\MonthlyJob;
use Dx\Payroll\Jobs\PayslipJob;

class PayrollController extends BaseController
{

    protected $repoPayrollSettings, $redisConfigForm, $repoEmployee, $zoho;

    public function __construct(PayrollSettingsInterface $repoPayrollSetings, RedisConfigFormInterface $redisConfigForm, RedisConfigFormRepository $redisControl, EmployeeInterface $repoEmployee, ZohoController $zohoController)
    {
        $this->repoPayrollSettings = $repoPayrollSetings;
        $this->redisConfigForm = $redisConfigForm;
        $this->redisControl = $redisControl;
        $this->repoEmployee = $repoEmployee;
        $this->zoho = $zohoController;
    }
    /**
     * Get config form from database
     *
     * @param $token
     * @return array|mixed
     */

    public function payrollProcess($request = [])
    {
        //$request->token middleWare
        $config = $this->redisConfigForm->getConfig();
        if (empty($config))
        {
            return $this->sendError('Empty config', [],404);
        }
        if (!isset($request->employee) ||
            !isset($request->module) || !in_array($request->module, ['dx_monthly', 'dx_payslip']) ||
            !isset($request->type) || !in_array($request->type, ['one', 'all']))
        {
            Log::channel('dx')->info('Missing Request: '. json_encode($request->post()));
            return $this->sendError('Missing or Invalid required parameters(employee, module, type)', [],400);
        }

        if($request->type == 'one'){
            $empItem = $this->explodePattern($request->employee, '-');
            $request->merge(['code' => $empItem['code']]);
        }
        if(!isset($request->month)){
            $request->merge(['month' => date('m-Y')]);
        }else{
            $request->merge(['month' => str_replace('/','-',$request->month)]);
        }
        $arrEmp = $this->repoEmployee->getEmployee($request->code);
        if(empty($arrEmp)){
            return $this->sendError('Not found employee', [],404);
        }
        $count = 0;
        $delayTime = 0;
        foreach ($arrEmp as $empInfo){
            if($empInfo->status != 1 || $empInfo->status_payroll != 1 || $empInfo->offerSalary->count() == 0){
                Log::channel('dx')->info($empInfo->code.': Nhân viên không có offer hoặc không được tính lương >>>'.PHP_EOL);
            }else{
                $request->merge(['code' => $empInfo->code]);
                if($request->module == 'dx_monthly'){
                    MonthlyJob::dispatch(base64_encode(json_encode($request->post())))->onQueue($request->module)->delay($delayTime);
                }else{
//                    PayslipJob::dispatch(base64_encode(json_encode($data)))->onQueue($data['module'])->delay($delayTime);
                }
                $count ++;
                if($count == 10){
                    $count = 0;
                    $delayTime = $delayTime + 5;
                }

            }
        }
        return $this->sendResponse('Success', 'End payrollProcess!');
    }

    public function explodePattern($txt = '', $gexp = '-', $num = 1)
    {

        $response['code']   = '';
        $response['title']  = '';

        if($gexp == ''){
            $gexp = '-';
        }

        if($txt != ''){

            $extEmp = explode($gexp, $txt);
            if(is_numeric(trim($extEmp[0]))){
                $code = trim($extEmp[0]);
                $title = trim($extEmp[1]);
            }else{
                $code = trim($extEmp[1]);
                $title = trim($extEmp[0]);
            }

            $response['code']       = $code;
            $response['title']      = $title;
        }
        return $response;
    }

    public function rangeSalaryDate($monthly = '', $arrPayrollConfig = [])
    {
        $fromSalary = $arrPayrollConfig[0]['from'];
        $toSalary = $arrPayrollConfig[0]['to'];
        // Kiểm tra có cùng tháng hay không
        ($fromSalary > $toSalary) ? $num = 1 : $num = 0;

        $fromDate   = date('Y-m-d', strtotime($fromSalary.'-'.$monthly));
        $toDate     = date('Y-m-d', strtotime($toSalary.'-'.$monthly. " +".$num." months"));
        $diff       = date_diff(date_create($fromDate),date_create($toDate));

        $countDate = $diff->format("%a");
        $arrDate = [];
        if($countDate == 0){
            $arrDate[] = $fromDate;
        }elseif ($countDate > 0){
            for ($i = 0; $i <= $countDate; $i++){
                $currenDate = date('Y-m-d', strtotime($fromDate. "+ " .$i. "days"));
                $arrDate[] = $currenDate;
            }
        }
        return $arrDate;
    }

    public function getPayrollConfig(){
        $response['code'] = 1;
        $response['data'] = [];
        $arrData = $this->redisControl->makeRedisConnection()->get('payroll_config');
        if(empty($arrData)){
            $arrConfig = $this->repoPayrollSettings->all();
            if(!empty($arrConfig)){
                $response['code'] = 0;
                $response['data'] = $arrConfig->toArray();
                $this->redisControl->makeRedisConnection()->set('payroll_config', json_encode($response));
            }
        }else{
            $response = json_decode($arrData,true);
        }
        return $response;
    }

    public function diffOfferSalary($inputData = [], $fromDay = '', $toDay = '')
    {
        $arrData            = [];
        $response           = [];
        $dataOfferSalary    = [];
        if(empty($inputData) || $fromDay == '' || $toDay == ''){
            return [];
        }

        foreach ($inputData as $offer){
            if($offer->from_date != null  && $offer->from_date != '0000-00-00'){
                $arrData[] = $offer->toArray();
            }
        }
        foreach ($arrData as $key => $offer){
            $next_Offer = $arrData[$key+1] ?? [];
            if(!empty($next_Offer)){
                if($offer['to_date'] == null || $offer['to_date'] == '0000-00-00' ||
                    (($offer['to_date'] != null && $offer['to_date'] != '0000-00-00') && strtotime($offer['to_date']) >= strtotime($next_Offer['from_date']))){
                    $dataOfferSalary[$offer['from_date']] = $offer;
                    $dataOfferSalary[$offer['from_date']]['to_date'] = date('Y-m-d', strtotime($next_Offer['from_date']. ' - 1 day'));
                }else{
                    $dataOfferSalary[$offer['from_date']] = $offer;
                }
            }else{
                //end offer
                $dataOfferSalary[$offer['from_date']] = $offer;
            }
        }
        foreach($dataOfferSalary as $date => $offer){
            if(strtotime($offer['from_date']) <= strtotime($toDay) && ($offer['to_date'] == null || $offer['to_date'] == '0000-00-00' || strtotime($offer['to_date']) >= strtotime($fromDay))){
                $response[$date] = $offer;
            }
        }
        return $response;
    }

    public function getLeaveWorking($config = [], $empCode = '', $startDate = '', $endDate = '')
    {
        $arrResponse = [];
        if(!empty($empCode)){
            $arrData = $this->zoho->searchLeaveWorking($config['leave']['getRecords'], $empCode, $startDate, $endDate, true);
            if(isset($arrData['status']) && ($arrData['status'] == -1 || $arrData['status'] == 1)){
                return [];
            }
            if(!empty($arrData)){
                foreach ($arrData as $key => $item){
                    if (strpos(strtolower($item['Leavetype']), "unpaid") !== false) {
                        continue;
                    }
                    $arrDetailLeave = $this->zoho->getRecordByID($item['Zoho_ID'], $config['leave']['getRecordByID']);
                    sleep(3);
                    if(isset($arrDetailLeave['status']) && $arrDetailLeave['status'] == 1){
                        return [];
                    }
                    if($arrDetailLeave['ApprovalStatus'] == 'Approved'){
                        if(!empty($arrDetailLeave['DayDetails'])){
                            foreach ($arrDetailLeave['DayDetails'] as $day => $val){
                                if($val['LeaveCount'] == '0.0') continue;
                                $day = date('Y-m-d', strtotime($day));
                                if(strtotime($day) >= strtotime($startDate) && strtotime($day) <= strtotime($endDate)){
                                    if(isset($arrResponse[$day]['leave_day'])){
                                        $arrResponse[$day]['leave_day'] += $val['LeaveCount'];
                                    }else{
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


    /**
     * Xử lý dữ liệu punch-in, punch-out
     *
     * @param array $config
     * @param array $arrPayrollConfig
     * @param array $dataPunch
     * @param array $dataShiftConfiguration
     * @param array $arrLeave
     * @param string $empCode
     * @param array $dataOfferSalary
     * @return array
     */

    public function workingBreakTime($overTimeForm, $config = [], $arrPayrollConfig = [], $dataPunch = [], $dataShiftConfiguration = [], $arrLeave = [], $empCode = '', $dataOfferSalary = [])
    {
        $response = [];
        if (isset($dataPunch['error']))
        {
            return [];
        }
        $fromDate   = array_key_first($dataPunch);
        $toDate     = array_key_last($dataPunch);
        $dataOvertime = $this->zoho->getOvertimeByEmployee($config[$overTimeForm]['getRecords'], $empCode, $fromDate, $toDate);
        if(isset($dataOvertime['status']) && $dataOvertime['status'] == 1){
            $dataOvertime = [];
        }

        foreach ($dataPunch as $date => $item)
        {
            $response[$date]['overtime_weekday']    = 0;
            $response[$date]['overtime_weekend']    = 0;
            $response[$date]['overtime_holiday']    = 0;
            $response[$date]['total_overtime']      = 0;
            $response[$date]['total_meal']          = 0;
            $response[$date]['punch_in']    = $item['FirstIn'];
            $response[$date]['punch_out']   = $item['LastOut'];
            if (array_key_exists($date, $dataShiftConfiguration))
            {
                //Các ngày không phải cuối tuần
                $response[$date]['date_type']   = 'Present';
                $response[$date]['holiday']     = 0;
                $totalHour = 0;
                if($item['Status'] == 'Absent')
                {
                    //Không đi làm
                    $response[$date]['date_type'] = 'Absent';
                }elseif (strpos($item['Status'], 'Holiday') !== false || strpos($item['Status'], 'Ngày lễ') !== false)
                {
                    //Ngày lễ
                    if (isset($arrLeave[$date]))
                    {
                        //Nghỉ thai sản
                        if (strpos($arrLeave[$date]['leave_type'], "Maternity Leave") !== false)
                        {
                            $totalHour = 0;
                        }else
                        {
                            $response[$date]['date_type'] = 'Holiday';
                            $response[$date]['holiday']   = 1;
                            $totalHour = 8;
                        }
                    }else
                    {
                        $response[$date]['date_type'] = 'Holiday';
                        $response[$date]['holiday']   = 1;
                        $totalHour = 8;
                    }
                    foreach ($dataOfferSalary as $offer) {
                        //Nếu ngày kết thúc mức lương mà nhỏ hơn ngày bắt đầu ký lương thì ko tính offer vào thời gian đó
                        if ($offer['to_date'] == '' || $offer['to_date'] == '0000-00-00')
                        {
                            $offer['to_date'] = $toDate;
                        }
                        if (strtotime($date) >= strtotime($offer['from_date']) && strtotime($date) <= strtotime($offer['to_date']))
                        {
                            $response[$date]['holiday']   = 1;
                            $totalHour = 8;
                            break;
                        }else
                        {
                            $response[$date]['holiday']   = 0;
                            $totalHour = 0;
                        }
                    }
                }else if ($item['Status'] == 'Weekend' || $item['Status'] == 'Weekend, Present')
                {
                    $totalHour = 0;
                }else
                {
                    $timeWork = explode(':', $item['TotalHours']);
                    $totalHour = $timeWork[0] + $timeWork[1]/60;
                }
                $response[$date]['actual_hour']         = $totalHour;
                $response[$date]['actual_day']          = $this->roundDay($totalHour, $arrPayrollConfig);
                $response[$date]['salary_day']          = $this->roundDay($totalHour, $arrPayrollConfig);
                $response[$date]['annual_leave']        = 0;
                $response[$date]['seniority_leave']     = 0;
                $response[$date]['maternity_leave']     = 0;
                $response[$date]['other_paid_leaves']   = 0;
                //Kiem tra xem ngay co leave ko
                if(!empty($arrLeave) && array_key_exists($date, $arrLeave))
                {
                    $response[$date] = array_merge($response[$date], $this->convertWorkingDayLeave($config, $arrPayrollConfig, $totalHour, $arrLeave[$date]));
                }
            }else
            {
                //Các ngày nghỉ cuối tuần
                $response[$date]['date_type']   = $item['Status'];
                $response[$date]['holiday']     = 0;
                $response[$date] = array_merge($response[$date], $this->convertWorkingDayLeave($config, $arrPayrollConfig,0, []));
            }
            if (!empty($dataOvertime)){
                foreach ($dataOvertime as $overtime){
                    if ($overtime['ApprovalStatus'] == 'Approved'){
                        if ($date == date('Y-m-d', strtotime($overtime['Date']))) {
                            $response[$date]['total_overtime'] += isset($overtime['Daytime_Hours']) ? $overtime['Daytime_Hours'] : 0;
                            if($item['Status'] == 'Weekend' || $item['Status'] == 'Weekend, Present'){
                                //OT ngày nghỉ thứ 7 CN
                                $response[$date]['overtime_weekend'] += isset($overtime['Daytime_Hours']) ? $overtime['Daytime_Hours'] : 0;
                            }elseif (strpos($item['Status'], 'compensatory leave weekend') !== false) {
                                $response[$date]['overtime_weekend'] += isset($overtime['Daytime_Hours']) ? $overtime['Daytime_Hours'] : 0;
                            }elseif (strpos($item['Status'], 'compensatory leave holiday') !== false) {
                                $response[$date]['overtime_holiday'] += isset($overtime['Daytime_Hours']) ? $overtime['Daytime_Hours'] : 0;
                            }elseif (strpos($item['Status'], 'Holiday') !== false || strpos($item['Status'], 'Ngày lễ') !== false) {
                                //OT vào ngày lễ
                                if($response[$date]['holiday'] > 0) {
                                    $response[$date]['overtime_holiday'] += isset($overtime['Daytime_Hours']) ? $overtime['Daytime_Hours'] : 0;
                                }else{
                                    $weekday = date("l", strtotime($date));
                                    $weekday = strtolower($weekday);
                                    if ($weekday == 'saturday' || $weekday == 'sunday') {
                                        $response[$date]['overtime_weekend'] += isset($overtime['Daytime_Hours']) ? $overtime['Daytime_Hours'] : 0;
                                    }
                                }
                            }else{
                                $response[$date]['overtime_weekday'] += isset($overtime['Daytime_Hours']) ? $overtime['Daytime_Hours'] : 0;
                            }
                            if($overtime['Meal'] == 'No' || $overtime['Meal'] == ''){
                                $response[$date]['total_meal'] += 0;
                            }else{
                                $response[$date]['total_meal'] += $overtime['Meal'];
                            }
                        }
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
        $response['actual_hour']        = 0;
        $response['actual_day']         = 0;
        $response['salary_day']         = 0;
        $response['annual_leave']       = 0;
        $response['other_paid_leaves']  = 0;
        $response['seniority_leave']    = 0;
        if(isset($arrLeave['leave_type'])){
            $leaveDay = $arrLeave['leave_day'];
            $leaveDayApproval   = $leaveDay;
            $totalDaySalary      = $this->roundDay($totalHour, $arrPayrollConfig) + $leaveDay;
            //Nghỉ thai sản
            if(strpos(strtolower($arrLeave['leave_type']), 'maternity leave') !== false){
                $response['maternity_leave']    = $leaveDayApproval;
                if($totalHour > 8){
                    $totalHour          = 8;
                }
                $response['actual_hour']        = round($totalHour, 2);
                $response['actual_day']         = $this->roundDay($totalHour, $arrPayrollConfig);
                $response['salary_day']         = $this->roundDay($totalHour, $arrPayrollConfig);
            }else{
                if($totalDaySalary > 1){
                    $salaryDay          = 1;
                    $actualDay          = (1 - $leaveDay) * 8;
                }else{
                    $salaryDay          = $totalDaySalary;
                    $actualDay          = $totalHour;
                }
                $response['actual_hour']        = round($actualDay, 2);
                $response['actual_day']         = $this->roundDay($actualDay, $arrPayrollConfig);
                $response['salary_day']         = $salaryDay;
                //Nghỉ cưới, tang
                if(strpos(strtolower($arrLeave['leave_type']), 'marriage leave') !== false || strpos(strtolower($arrLeave['leave_type']), "bereavement leave") !== false){
                    $response['other_paid_leaves']  = $leaveDayApproval;
                }
                if(strpos(strtolower($arrLeave['leave_type']), 'annual leave') !== false){
                    $response['annual_leave']         = $leaveDayApproval;
                }
                if(strpos(strtolower($arrLeave['leave_type']), 'seniority leave') !== false){
                    $response['seniority_leave']         = $leaveDayApproval;
                }
                if (strpos(strtolower($arrLeave['leave_type']), 'after 5 years') !== false || strpos(strtolower($arrLeave['leave_type']), 'after 5ys') !== false) {
                    $response['annual_leave']         = $leaveDayApproval;
                }
            }
        }
        return $response;
    }

    public function roundDay($totalHour = 0, $arrPayrollConfig = [])
    {
        if($totalHour == ''){
            $totalHour = 0;
        }
        $halfDay = $arrPayrollConfig[0]['haft_working_day'];
        $fullDay = $arrPayrollConfig[0]['working_day'];
        if($totalHour >= $fullDay){
            $day = 1;
        }elseif ($totalHour >= $halfDay){
            $day = 0.5;
        }else{
            $day = 0;
        }
        return $day;
    }
}
