<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\Eloquent\RecordsRepository;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Dx\Payroll\Repositories\SectionsInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Illuminate\Support\Facades\Log;

class PayslipController extends BaseController
{

    protected $payroll, $repoSections, $zoho, $redisConfigForm, $records, $zohoForm;

    public function __construct(PayrollController $payrollController, SectionsInterface $repoSections
        , RedisConfigFormInterface $redisConfigForm, RecordsRepository $records,ZohoFormInterface $zohoForm)
    {
        $this->redisConfigForm = $redisConfigForm;
        $this->payroll = $payrollController;
        $this->repoSections = $repoSections;
        $this->zoho = ZohoPeopleIntegration::getInstance();
        $this->records = $records;
        $this->zohoForm = $zohoForm;
    }

    public function processPayslip($arrInput){
        $empCode    = $arrInput['code'] ?? '';
        $month      = $arrInput['month'] ?? '';
        $monthly    = str_replace('-','/', $month);
        $code       = $empCode ."-". $monthly;
        $log        = $empCode.': <<< Start'.PHP_EOL;

        $config = $this->redisConfigForm->getConfig();
        if(empty($config)){
            $log .= ' ERROR : === processWorkingTime Không có config >>>'.PHP_EOL;
            Log::channel('dx')->info($log);
            return 0;
        }
        // Get Data Payroll
        $i = 0;
        $allMasterData = [];
        $allFactor = [];
        $allFomular = [];
        $masterData = [''];
        $factor = [''];
        $formular = [''];
        do{
            if($masterData){
                $masterData     = $this->records->getRecords($arrInput['config_payroll']['form_master_form_name'], $i * 200, 200);
                $allMasterData  = array_merge($allMasterData, $masterData);
            }

            if($factor){
                $factor         = $this->records->getRecords($arrInput['config_payroll']['factor_master_form_name'], $i * 200, 200);
                $allFactor      = array_merge($allFactor, $factor);
            }

            if($formular){
                $formular       = $this->records->getRecords($arrInput['config_payroll']['fomular_form_name'], $i * 200, 200);
                $allFomular     = array_merge($allFomular, $formular);
            }
            $i++;
        }while($masterData && $factor && $formular);

        // Get data từ hệ thống
        $monthlyWorking          = [];
        $monthlyWorkingExist = $this->zoho->searchPayroll($config[$arrInput['config_payroll']['monthly_form_name']]['getRecords'], $code);
        if(!isset($monthlyWorkingExist['errors'])){
            $monthlyWorking = $monthlyWorkingExist[0];
        }

        $employeeData = $this->records->searchRecords($arrInput['config_payroll']['employee_form_name'], $arrInput['config_payroll']['employee_code_field'], $empCode)[0];
        $employeeData = array_merge($monthlyWorking, $employeeData);
        // tổng hợp công thức và dữ liệu
        $sourceSystem = [];
        $sourceFormular = [];
        $sourcePayslip = [];
        foreach ($allFactor as $factor){
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
