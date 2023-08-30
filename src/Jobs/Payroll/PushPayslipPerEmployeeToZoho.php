<?php

namespace Dx\Payroll\Jobs\Payroll;

use Carbon\Carbon;
use Dx\Payroll\Http\Controllers\Api\Payroll\PayslipController;
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

class PushPayslipPerEmployeeToZoho implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $employee;
    private $month;

    private $zohoLib;
    private $zohoForm;
    private $zohoRecord;
    private $zohoRecordValue;
    private $payslipApiController;

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
        $this->payslipApiController = app(PayslipController::class);

        $employeeFormLinkName       = Env::get('EMPLOYEE_FORM_LINK_NAME');
        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');
        $formMasterDataFormLinkName = Env::get('PAYROLL_FORM_MASTER_DATA_FORM_LINK_NAME');
        $salaryFactorFormLinkName = Env::get('PAYROLL_SALARY_FACTOR_FORM_LINK_NAME');
        $formulaSourceFormLinkName = Env::get('PAYROLL_FORMULA_SOURCE_FORM_LINK_NAME');
        $payslipFormLinkName = Env::get('PAYROLL_PAYSLIP_FORM_LINK_NAME');

        $employee = $this->employee;
        $month    = $this->month;

        $empCode = $employee[$employeeIdNumberFieldLabel];
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;

        $masterDataFormCollect = $this->payslipApiController->getAllDataFormLinkName($formMasterDataFormLinkName, $this->zohoRecord);
        $salaryFactorCollect = $this->payslipApiController->getAllDataFormLinkName($salaryFactorFormLinkName, $this->zohoRecord);
        $formulaSourceCollect = $this->payslipApiController->getAllDataFormLinkName($formulaSourceFormLinkName, $this->zohoRecord);

        $cacheDataForm = [];
        /** formEav*/
        $formEav = $this->zohoForm->has('attributes', 'sections', 'sections.attributes')->with('attributes', 'sections', 'sections.attributes')->where('form_link_name', $payslipFormLinkName)->first();
        if (is_null($formEav)) {
            Log::channel('dx')->info('formEav is null ::: empCode :::' . $empCode);
            throw new \ErrorException('formEav is null');
        }

        /** employee information */
        $employeeData = $this->zohoRecord->getRecords($employeeFormLinkName, 0, 200, [$employeeIdNumberFieldLabel => $empCode])[0];
        $cacheDataForm[$employeeFormLinkName] = $employeeData;

        /* assign value to key */
        $keyWithVals = $salaryFactorCollect->reject(function ($factor) {
            return $factor['type'] != 'Có sẵn trên hệ thống' || empty($factor['field_name']);
        })->map( function($factor) use (&$cacheDataForm, $masterDataFormCollect, $employeeData) {
            $masterDataFormZohoId = $factor['field_name'];
            
            $masterDataByFactor = $masterDataFormCollect->filter(function ($masterData) use ($masterDataFormZohoId) {
                if ($masterData['Zoho_ID'] == $masterDataFormZohoId) {
                    return $masterData;
                }
            })->values()->first();
            
            if (!empty($masterDataByFactor)) {
                $searchParams = [];
                if ($factor['Condition'] == 'Theo nhân viên') {
                    $searchParams = array_merge($searchParams, ['employee' => $employeeData['Zoho_ID']]);
                }
                return [ $factor['abbreviation'] => $this->payslipApiController->replaceSystemDataToFactor($cacheDataForm, $masterDataByFactor['form_label'],
                                                                                    $masterDataByFactor['label_name'], $searchParams)];
            }
        })->values()->collapse()->all();

        /* re-map fomula with value */
        $maths = ['+', '-', '*', '/', '(', ')'];
        $fomulaVals = $salaryFactorCollect->reject(function ($factor) {
            return $factor['type'] != 'Tính theo công thức';
        })->map( function($factor) use ($formulaSourceCollect, $maths, $keyWithVals) {
            $factorZohoId = $factor['Zoho_ID'];
            $formulaByFactor = $formulaSourceCollect->filter(function ($fomula) use ($factorZohoId) {
                if ($fomula['field'] == $factorZohoId) {
                    return $fomula;
                }
            })->values()->first();

            $fomulaString = $formulaByFactor['formula'];

            $arrExpression = explode('|', str_replace($maths, '|', $fomulaString));
            foreach ($arrExpression as $expression) {
                if (empty($expression)) continue;

                if (isset($keyWithVals[$expression]) && !is_array($keyWithVals[$expression])) {
                    $val = empty($keyWithVals[$expression]) ? 0 : $keyWithVals[$expression];
                    $fomulaString = preg_replace('/\b'.$expression.'\b/u', $val, $fomulaString);
                }
            }

            return [ $factor['abbreviation'] => $fomulaString];
        })->values()->collapse()->all();

        /* check if exist record */
        $payslipExists = $this->zohoRecord->getRecords($payslipFormLinkName, 0, 1, [
            'code' => $code,
            'salary_period'=> $monthly
        ]);
        $payslipExist = isset($payslipExists[0]) ? $payslipExists[0] : [];

        list($constantConfig, $constantVals) = $this->payslipApiController->mappingConstantVals($month, $employeeData, $payslipExist);
        $this->payslipApiController->mappingContantValueToFomulaValsAndKeyVals($constantVals, $fomulaVals, $keyWithVals);
        $this->payslipApiController->sortFomulaSource($fomulaVals, $keyWithVals);
        $this->payslipApiController->caculateFomula($fomulaVals, $keyWithVals, $constantConfig);

        $standardWorkingDay = $keyWithVals['ngay_cong_chinh_thuc'] ?? 0;
        $standardWorkingDayProbation = $keyWithVals['ngay_cong_thu_viec'] ?? 0;

        $inputData = [];
        $inputData['employee1']                      = $employeeData['Zoho_ID'];
        $inputData['salary_period']                  = $monthly;
        $inputData['code']                           = $code;
        $inputData['standard_working_day']           = convert_decimal_length($standardWorkingDay, 1);
        $inputData['standard_working_day_probation'] = convert_decimal_length($standardWorkingDayProbation, 1);

        $tabularData = $this->payslipApiController->processTabularData($formEav, $constantVals, $keyWithVals, $payslipExist);

        $payslipLogDetails = [];
        $payslipLogDetails[] = $inputData;
        $payslipLogDetails[] = $tabularData;
        if (!empty($payslipExist)) {
            $payslipZohoId = $payslipExist['Zoho_ID'];
            $responseUpdatePayslip = $this->zohoLib->updateRecord($payslipFormLinkName, $inputData, $tabularData, $payslipZohoId);
            $payslipLogDetails[] = $responseUpdatePayslip;
            if (!isset($responseUpdatePayslip['result']) || !isset($responseUpdatePayslip['result']['pkId'])) {
                Log::channel('dx')->info('Something error. Can not update payslip exist Zoho_ID:' . $payslipZohoId, $payslipLogDetails);
                throw new \ErrorException('Something error. Can not update payslip exist Zoho_ID: ' . $payslipZohoId);
            }

            Log::channel('dx')->info("update exist payslip with zoho id [$payslipZohoId]", $payslipLogDetails);
        } else {
            $rspInsert = $this->zohoLib->insertRecord($payslipFormLinkName, $inputData, 'yyyy-MM-dd');
            $payslipLogDetails[] = $rspInsert;
            if (!isset($rspInsert['result']) || !isset($rspInsert['result']['pkId'])) {
                Log::channel('dx')->info('Something error. Can not create new payslip.', $payslipLogDetails);
                throw new \ErrorException('Something error. Can not create new payslip.');
            }
    
            $zohoId = $rspInsert['result']['pkId'];
            $rspUpdate = $this->zohoLib->updateRecord($payslipFormLinkName, $inputData, $tabularData, $zohoId, 'yyyy-MM-dd');
            $payslipLogDetails[] = $rspUpdate;
            if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
                Log::channel('dx')->info('Something error. Can not update payslip with Zoho_ID:' . $zohoId, $payslipLogDetails);
                throw new \ErrorException('Something error. Can not update payslip with Zoho_ID: ' . $zohoId);
            }

            Log::channel('dx')->info("update exist payslip with zoho id [$zohoId]", $payslipLogDetails);
        }
    }
}
