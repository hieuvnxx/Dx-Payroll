<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Repositories\PayrollSettingsInterface;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Dx\Payroll\Http\Controllers\EmployeeController;
use Dx\Payroll\Http\Controllers\BaseController;
use Dx\Payroll\Jobs\MonthlyJob;
use Dx\Payroll\Jobs\PayslipJob;
use Dx\Payroll\Helpers\getAPI;

class PayrollController extends BaseController
{

    protected $repoPayrollSettings, $redisConfigForm;

    public function __construct(PayrollSettingsInterface $repoPayrollSetings, RedisConfigFormInterface $redisConfigForm)
    {
        $this->repoPayrollSettings = $repoPayrollSetings;
        $this->redisConfigForm = $redisConfigForm;
    }
    /**
     * Get config form from database
     *
     * @param $token
     * @return array|mixed
     */

    public function payrollProcess($request = [])
    {
        if(isset($request->token)){
            $config = $this->redisConfigForm->getConfigByToken();
            if(empty($config)){
                return $this->sendError('Empty config', [],404);
            }
            if(!isset($request->employee) || !isset($request->module) || !in_array($request->module, ['dx_monthly', 'dx_payslip']) || !isset($request->type) || !in_array($request->type, ['one', 'all'])){
                Log::channel('dx')->info('Missing Request: '. json_encode($request->post()));
                return $this->sendError('Missing or Invalid required parameters(employee, module, type)', [],400);
            }
        }else{
            return $this->sendError('Missing Token parameter', [],400);
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
        $arrEmp = app(EmployeeController::class)->getAllEmployee($request);
        if($arrEmp->count() == 0){
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
//                    MonthlyJob::dispatch(base64_encode(json_encode($request->post())))->onQueue($request->module)->delay($delayTime);
                }elseif ($request->module  == 'dx_payslip'){
//                    PayslipJob::dispatch(base64_encode(json_encode($data)))->onQueue($data['module'])->delay($delayTime);
                }else{
                    return 0;
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

        $redis      = Redis::connection();
        $keyRedis = 'payroll_config';

        $arrData    = $redis->get($keyRedis);
        if(empty($arrData)){
            $arrConfig = $this->repoPayrollSettings->getSettings();
            if(!empty($arrConfig)){
                $response['code'] = 0;
                $response['data'] = $arrConfig->toArray();
                $redis->set($keyRedis, json_encode($response));
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
        if($inputData->count() == 0 || $fromDay == '' || $toDay == ''){
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
}
