<?php


namespace Dx\Payroll\Http\Controllers\Api\Payroll;

use Dx\Payroll\Http\Controllers\Api\Payroll\PayrollController;
use Dx\Payroll\Http\Requests\ApiPayslipByCode;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;

/**
 * insert database zoho form
 */
class PayslipController extends PayrollController
{
    /**
    * 
    */
    public function processAll(ApiPayslipByCode $request)
    {
        
    }

    /**
    * 
    */
    public function processByCode(ApiPayslipByCode $request)
    {
        $employeeFormLinkName       = Env::get('EMPLOYEE_FORM_LINK_NAME');
        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');
        $constantConfigFormLinkName = Env::get('PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME');
        $monthlyWorkingTimeFormLinkName = Env::get('PAYROLL_MONTHY_WORKING_TIME_FORM_LINK_NAME');
        $formMasterDataFormLinkName = Env::get('PAYROLL_FORM_MASTER_DATA_FORM_LINK_NAME');
        $salaryFactorFormLinkName = Env::get('PAYROLL_SALARY_FACTOR_FORM_LINK_NAME');
        $formulaSourceFormLinkName = Env::get('PAYROLL_FORMULA_SOURCE_FORM_LINK_NAME');

        $empCode = $request->code;
        $month   = $request->month;
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;

        DB::enableQueryLog();

        $formulaSourceCollect = $this->getAllDataFormLinkName($formulaSourceFormLinkName);
        $salaryFactorCollect = $this->getAllDataFormLinkName($salaryFactorFormLinkName);
        $masterDataFormCollect = $this->getAllDataFormLinkName($formMasterDataFormLinkName);

        /** employee information */
        $employee = $this->zohoRecord->getRecords($employeeFormLinkName, 0, 200, [$employeeIdNumberFieldLabel => $empCode])[0];

        /** fetch data working time for employee */
        $monthlyWorkingsByCode = $this->zohoRecord->getRecords($monthlyWorkingTimeFormLinkName, 0, 200, ['code' => $code, 'salary_period' => $monthly]);
        $existMonthlyWorkingTime = !empty($monthlyWorkingsByCode) ? $monthlyWorkingsByCode[0] : [];


        /**
         * TODO
         */
        $sourceAll = $salaryFactorCollect->map(function($item) use ($formulaSourceCollect) {
            dd($item, $formulaSourceCollect->count());
        });
        dd(1);

        // tổng hợp công thức và dữ liệu
        $sourceSystem = [];
        $sourceFormular = [];
        $sourcePayslip = [];
        foreach ($salaryFactorCollect as $salaryFactor){
            dd($salaryFactor);

            if($factor['type'] == 'Có sẵn trên hệ thống'){
                foreach ($allMasterData as $form){
                    if($factor['field_name'] == $form['Zoho_ID']){
                        $sourceSystem[$factor['abbreviation']] = array_merge($form, $factor);
                    };
                }
            };
            if($factor['type'] == 'Tính theo công thức'){
                foreach ($allFomular as $formula){
                    if($formula['field'] == $factor['Zoho_ID'] && isset($formula['formula'])){
                        $sourceFormular[$factor['abbreviation']] = $formula['formula'];
                    }
                }
            }
            if($factor['type'] == 'Cập nhật trên bảng lương'){
                $sourcePayslip[$factor['factor']] = $factor['abbreviation'];
            }
        }

        dd(1);


        // thay data vào các trường đơn
        if(!empty($sourceSystem)){
            foreach ($sourceSystem as $keySystem => $valueSystem){
                if(!empty($employeeData)){
                    foreach ($employeeData as $key => $value){
                        if($key == $valueSystem['field_label']){
                            $sourceSystem[$keySystem] = (double)$value;
                        }
                    }
                }
            }
        }
        // Tổng hợp lại công thức
        $arrExpression = [];
        $math = ['+', '-', '*', '/', '(', ')'];
        foreach ($sourceFormular as $res => &$fomularRes){
            $arrExpression = explode('|', str_replace($math, '|', $fomularRes));
            foreach ($arrExpression as $expression){
                if(!empty($expression) && !is_numeric($expression)){
                    foreach ($sourceFormular as $req => $fomularReq){
                        if($expression == $req){
                            $sourceFormular[$res] = str_replace($req, '('.$fomularReq.')', $fomularRes);
                        }
                    }
                }
                // thay thế dữ liệu
                foreach ($sourceSystem as $sysName => $sysData){
                    if($expression == $sysName && !is_array($sysData)){
                        $sourceFormular[$res] = str_replace($sysName, $sysData, $fomularRes);
                    }
                }
            }
        }

        //eval
        $totalFomular = [];
        foreach ($sourceFormular as $key => $total){
            foreach ($sourcePayslip as $field_name => $field_label){
                if($key == $field_label){
                    if(preg_match('/^[-+*\/()\d\.\s]+$/', $total)){
                        $totalFomular[$field_name] = eval("return {$total};");
                    }else{
                        $totalFomular[$field_name] = 0;
                    }
                }
            }
        }

        $standardWorkingDay = $employeeData['standard_working_day'] ?? 0;
        $standardWorkingDayProbation = $employeeData['standard_working_day_probation'] ?? 0;
        $data = [];
        $data['employee1']                      = $employeeData['Zoho_ID'];
        $data['salary_period']                  = $monthly;
        $data['code']                           = $code;
        $data['standard_working_day']           = intval($standardWorkingDay);
        $data['standard_working_day_probation'] = intval($standardWorkingDayProbation);

        // Kiểm tra xem đã tồn tại monthly working report chưa
        $payslipIs          = [];
        $tabularSections    = [];
        $payslip = $this->zoho->searchPayroll($config[$arrInput['config_payroll']['payslip_form_name']]['getRecords'], $code);
        if(!isset($payslip['errors'])){
            $payslipIs = $payslip[0];
        }

        // Xử lý tabular
        $listField = $this->zohoForm->getFieldOfForm($arrInput['config_payroll']['payslip_form_name']);
        if(!$listField->isEmpty()){
            foreach ($listField as $fieldID){
                if(!is_null($fieldID->section_id)){
                    foreach ($totalFomular as $formular => $total){
                        if($fieldID->field_name == $formular){
                            $tabularSections[$fieldID->section_id]['add'][0][$fieldID->field_label] = $total;
                        }
                    }
                    if(!empty($payslipIs['tabularSections'])){
                        foreach ($payslipIs['tabularSections'] as $key => $tabular){
                            if(empty($tabular)){
                                continue;
                            }
                            foreach ($tabular as $item){
                                if(!isset($item['tabular.ROWID'])){
                                    continue;
                                }
                                if($key == $fieldID->section_name){
                                    $tabularSections[$fieldID->section_id]['delete'][]    = $item['tabular.ROWID'];
                                }
                            }
                        }
                    }
                }
            }
        }

        if(!empty($payslipIs)){
            $zohoId     = $payslipIs['Zoho_ID'];
            $actionPay  = $config[$arrInput['config_payroll']['payslip_form_name']]['updateRecord'];
        }else{
            $zohoId     = '';
            $actionPay  = $config[$arrInput['config_payroll']['payslip_form_name']]['insertRecord'];
        }

        // Insert if not found
        if(empty($zohoId)){
            $insertPayslip = $this->zoho->createdOrUpdated($actionPay, $data, [], $zohoId, 'yyyy-MM-dd');
            if(isset($insertPayslip['result']['pkId'])){
                $log .= 'Create payslip success'.PHP_EOL;
                $zohoId = $insertPayslip['result']['pkId'] ?? '';
                $actionPay = $config[$arrInput['config_payroll']['payslip_form_name']]['updateRecord'];
            }else{
                $log .= 'Create payslip failed'.PHP_EOL;
                Log::channel('dx')->info($log);
                return 0;
            }
        }

        if(!empty($zohoId)) {
            $response = $this->zoho->createdOrUpdated($actionPay, $data, $tabularSections, $zohoId, 'yyyy-MM-dd');
            if (isset($response['result']['pkId'])) {
                $log .= 'Update Success.' . PHP_EOL;
            } else {
                $log .= 'Update Failed' .json_encode($response). PHP_EOL;
            }
        }
        Log::channel('dx')->info($log);
        return 200;
    }

    
}
