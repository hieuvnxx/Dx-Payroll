<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Helpers\getAPI;
use Dx\Payroll\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Log;

class MonthlyController extends BaseController
{
    protected $payrollController;

    public function __construct(PayrollController $payrollController)
    {
        $this->payrollController = $payrollController;
    }

    public function processMonthly($arrInput){
        $log        = '';
        $empCode    = $arrInput['code'] ?? '';
        $month      = $arrInput['month'] ?? '';
        $zohoId     = $arrInput['zoho_id'] ?? '';

        $payrollSettings = $this->payrollController->getPayrollConfig();
        if(isset($payrollSettings['code']) && $payrollSettings['code'] == 1 || empty($payrollSettings['data'])){
            $log .= ' ERROR : === processWorkingTime Không có PayrollConfig >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        $log .= $empCode.': <<< BEGIN processWorkingTime'.PHP_EOL;
        $arrEmployee = app(EmployeeController::class)->getEmployeeByCode($empCode);

        $rangeDate = $payControl->rangeSalaryDate($month, $payrollSettings['data']);
        if(empty($rangeDate)){
            $log .= 'Không tồn tại tháng cần report >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        $fromDate = date("Y-m-d", strtotime(reset($rangeDate)));
        $toDate = date("Y-m-d", strtotime(end($rangeDate)));
        $dataOfferSalary = $payControl->diffOfferSalary($arrEmployee[0]->offerSalary, $fromDate, $toDate);
        if(empty($dataOfferSalary)){
            $log .= 'Không có Offer Salary >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }

        $dataPunch = getAPI::getAttendanceByEmployee($config['attendance']['getUserReport'], $empCode, $fromDate, $toDate);
        ksort($dataPunch);
        dd($dataPunch);
//        foreach ($empInfo->offerSalary as $offer){
//            if(($offer->from_date != '0000-00-00' && $offer->from_date != null && strtotime($offer->from_date) < strtotime(end($rangeDate))) || ($offer->from_date != '0000-00-00' && $offer->from_date != null && strtotime($offer->from_date) < strtotime(end($rangeDate)))){
//                dd(reset($rangeDate), end($rangeDate));
//            }
//        }
    }
}
