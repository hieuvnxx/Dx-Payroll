<?php


namespace Dx\Payroll\Http\Controllers\Api\Payroll;

use Carbon\Carbon;
use DivisionByZeroError;
use Dx\Payroll\Http\Controllers\Api\Payroll\PayrollController;
use Dx\Payroll\Http\Requests\ApiPayslipAll;
use Dx\Payroll\Http\Requests\ApiPayslipByCode;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Jobs\Payroll\PushPayslipPerEmployeeToZoho;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;

/**
 * insert database zoho form
 */
class PayslipController extends PayrollController
{
    protected $zohoLib;
    protected $zohoForm;
    protected $zohoRecord;
    protected $zohoRecordValue;
    
    public function __construct(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord, ZohoRecordValueInterface $zohoRecordValue)
    {
        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $this->zohoForm = $zohoForm;
        $this->zohoRecord = $zohoRecord;
        $this->zohoRecordValue = $zohoRecordValue;
    }

    /**
    * 
    */
    public function processAll(ApiPayslipAll $request)
    {
        $month   = $request->month;
        $employeeFormLinkName       = Env::get('EMPLOYEE_FORM_LINK_NAME');
        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');

        $offset = 0;
        $limit  = 100;
        
        $arrEmpCode = [];
        while (true) {
            $employees = $this->zohoRecord->getRecords($employeeFormLinkName, $offset, $limit, ['Employeestatus' => "Active"]);
            if (empty($employees)) {
                break;
            }

            foreach($employees as $employee) {
                $arrEmpCode[] = $employee[$employeeIdNumberFieldLabel];
                
                $data = [
                    'employee' => $employee,
                    'month' => $month
                ];
                PushPayslipPerEmployeeToZoho::dispatch($data);
            }

            $offset += $limit;
        }

        return $this->sendResponse($request, 'Successfully.', [ 'empCodes' => $arrEmpCode, 'total' => count($arrEmpCode)]);
    }

    /**
    * 
    */
    public function processByCode(ApiPayslipByCode $request)
    {
        $employeeFormLinkName       = Env::get('EMPLOYEE_FORM_LINK_NAME');
        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');
        $formMasterDataFormLinkName = Env::get('PAYROLL_FORM_MASTER_DATA_FORM_LINK_NAME');
        $salaryFactorFormLinkName = Env::get('PAYROLL_SALARY_FACTOR_FORM_LINK_NAME');
        $formulaSourceFormLinkName = Env::get('PAYROLL_FORMULA_SOURCE_FORM_LINK_NAME');
        $payslipFormLinkName = Env::get('PAYROLL_PAYSLIP_FORM_LINK_NAME');

        $empCode = $request->code;
        $month   = $request->month;
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;

        $masterDataFormCollect = $this->getAllDataFormLinkName($formMasterDataFormLinkName, $this->zohoRecord);
        $salaryFactorCollect = $this->getAllDataFormLinkName($salaryFactorFormLinkName, $this->zohoRecord);
        $formulaSourceCollect = $this->getAllDataFormLinkName($formulaSourceFormLinkName, $this->zohoRecord);

        $cacheDataForm = [];
        /** formEav*/
        $formEav = $this->zohoForm->has('attributes', 'sections', 'sections.attributes')->with('attributes', 'sections', 'sections.attributes')->where('form_link_name', $payslipFormLinkName)->first();
        if (is_null($formEav)) {
            return $this->sendError($request, 'Something error with monthly working time form in database.');
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
                return [ $factor['abbreviation'] => $this->replaceSystemDataToFactor($cacheDataForm, $masterDataByFactor['form_label'],
                                                                                    $masterDataByFactor['field_label'], $searchParams)];
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

        list($constantConfig, $constantVals) = $this->mappingConstantVals($month, $employeeData, $payslipExist);
        $this->mappingContantValueToFomulaValsAndKeyVals($constantVals, $fomulaVals, $keyWithVals);
        $this->sortFomulaSource($fomulaVals, $keyWithVals);
        $this->caculateFomula($fomulaVals, $keyWithVals, $constantConfig);
        
        $standardWorkingDay = $keyWithVals['ngay_cong_chinh_thuc'] ?? 0;
        $standardWorkingDayProbation = $keyWithVals['ngay_cong_thu_viec'] ?? 0;

        $inputData = [];
        $inputData['employee1']                      = $employeeData['Zoho_ID'];
        $inputData['salary_period']                  = $monthly;
        $inputData['code']                           = $code;
        $inputData['standard_working_day']           = convert_decimal_length($standardWorkingDay, 1);
        $inputData['standard_working_day_probation'] = convert_decimal_length($standardWorkingDayProbation, 1);

        $tabularData = $this->processTabularData($formEav, $constantVals, $keyWithVals, $payslipExist);

        $payslipLogDetails = [];
        if (!empty($payslipExist)) {
            $payslipZohoId = $payslipExist['Zoho_ID'];
            $responseUpdatePayslip = $this->zohoLib->updateRecord($payslipFormLinkName, $inputData, $tabularData, $payslipZohoId);
            $payslipLogDetails[] = $responseUpdatePayslip;
            if (!isset($responseUpdatePayslip['result']) || !isset($responseUpdatePayslip['result']['pkId'])) {
                return $this->sendError($request, 'Something error. Can not update exist payslip with zoho id : '. $payslipZohoId, [$responseUpdatePayslip, $inputData, $tabularData]);
            }

            Log::channel('dx')->info("update exist payslip with zoho id [$payslipZohoId]", [$responseUpdatePayslip, $inputData, $tabularData]);
        } else {
            $rspInsert = $this->zohoLib->insertRecord($payslipFormLinkName, $inputData, 'yyyy-MM-dd');
            $payslipLogDetails[] = $rspInsert;
            if (!isset($rspInsert['result']) || !isset($rspInsert['result']['pkId'])) {
                return $this->sendError($request, 'Something error. Can not insert new record payslip in to zoho.', [$rspInsert, $inputData]);
            }
    
            $zohoId = $rspInsert['result']['pkId'];
            $rspUpdate = $this->zohoLib->updateRecord($payslipFormLinkName, $inputData, $tabularData, $zohoId, 'yyyy-MM-dd');
            $payslipLogDetails[] = $rspUpdate;
            if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
                return $this->sendError($request, 'Something error. Can not update payslip with zoho id : '. $zohoId, [$rspUpdate, $inputData, $tabularData]);
            }

            Log::channel('dx')->info("create and update [$zohoId]", [$rspUpdate, $inputData, $tabularData]);
        }

        return $this->sendResponse($request, 'Successfully.', $payslipLogDetails);
    }

    public function caculateFomula(&$fomulaVals, &$keyWithVals, $constantConfig)
    {
        $this->getPersonalIncomeTax($keyWithVals, $constantConfig);

        if (empty($fomulaVals)) return;

        $maths = ['+', '-', '*', '/', '(', ')'];
        foreach ($fomulaVals as $label => &$fomulaString) {
            $arrExpression = explode('|', str_replace($maths, '|', $fomulaString));
            foreach ($arrExpression as $expression) {
                if (empty($expression) || is_numeric($expression)) continue;

                if (isset($keyWithVals[$expression]) && !is_array($keyWithVals[$expression])) {
                    $val = empty($keyWithVals[$expression]) ? 0 : $keyWithVals[$expression];
                    $fomulaString = preg_replace('/\b'.$expression.'\b/u', $val, $fomulaString);
                }
            }
        }

        $this->sortFomulaSource($fomulaVals, $keyWithVals);
    }

    public function replaceSystemDataToFactor(&$cacheDataForm, $formLinkName, $fieldLabel, $searchParams = [], $zohoId = null)
    {
        if (!empty($searchParams) && !empty($cacheDataForm[$formLinkName.$fieldLabel])) {
            return $cacheDataForm[$formLinkName.$fieldLabel][$fieldLabel] ?? '';
        } elseif(!empty($cacheDataForm[$formLinkName]) && empty($searchParams)) {
            return $cacheDataForm[$formLinkName][$fieldLabel] ?? '';
        }

        $val = null;
        if (is_null($zohoId)) {
            $formData = $this->zohoRecord->getRecords($formLinkName, 0, 200, $searchParams);
            if (!empty($searchParams)) {
                if (!empty($formData)) {
                    $cacheDataForm[$formLinkName.$fieldLabel] = $formData[0];
                    $val = $cacheDataForm[$formLinkName.$fieldLabel][$fieldLabel] ?? '';
                } else {
                    $cacheDataForm[$formLinkName.$fieldLabel] = $formData;
                    $val = $cacheDataForm[$formLinkName.$fieldLabel][$fieldLabel] ?? '';
                }
            } else {
                if (!empty($formData)) {
                    $cacheDataForm[$formLinkName] = $formData[0];
                    $val = $cacheDataForm[$formLinkName][$fieldLabel] ?? '';
                } else {
                    $cacheDataForm[$formLinkName] = $formData;
                    $val = $cacheDataForm[$formLinkName][$fieldLabel] ?? '';
                }
            }
        } else {
            $formData = $this->zohoRecord->getRecordByZohoID($formLinkName, $zohoId);
            $cacheDataForm[$formLinkName] = $formData;
            $val = $cacheDataForm[$formLinkName][$fieldLabel];
        }
        
        return $val;
    }

    /**
    * mappingConstantVals
    */
    public function mappingConstantVals($month, $employeeData, $payslipExist)
    {
        $constantVals = [];

        // Khoản trừ cá nhân đi theo thông tin employee
        $constantVals['khoan_tru_ca_nhan']['total'] = 0;
        $constantVals['khoan_tru_ca_nhan']['detai'] = [];

        // Các phụ cấp trong form Cấu hình hằng số thuộc bảng phụ cấp
        $constantVals['phu_cap_khac']['total'] = 0;
        $constantVals['phu_cap_khac']['detai'] = [];
        $constantVals['phu_cap_an_trua']['total'] = 0;
        $constantVals['phu_cap_an_trua']['detai'] = [];
        $constantVals['phu_cap_xang_xe']['total'] = 0;
        $constantVals['phu_cap_xang_xe']['detai'] = [];
        $constantVals['phu_cap_dien_thoai']['total'] = 0;
        $constantVals['phu_cap_dien_thoai']['detai'] = [];

        // Thưởng lễ được lấy từ form Cấu hình hằng số
        $constantVals['thuong_le']['total'] = 0;
        $constantVals['thuong_le']['detai'] = [];

        // các trường hệ thống lấy từ payslip có sẵn để cập nhật lại công thức
        $constantVals['tong_hoan_thue_tncn']['total'] = 0;
        $constantVals['truy_thu_thue_tncn']['total'] = 0;
        $constantVals['truy_thu_khac']['total'] = 0;
        $constantVals['hoan_bhxh']['total'] = 0;
        $constantVals['tam_ung']['total'] = 0;

        $constantConfigFormLinkName = Env::get('PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME');
        $constantConfigs = $this->zohoRecord->getRecords($constantConfigFormLinkName);
        $constantConfig = $constantConfigs[0];

        list($fromSalary, $toSalary) = payroll_range_date($month, $constantConfig['from_date'], $constantConfig['to_date']);
        $fromSalary = Carbon::createFromFormat('Y-m-d', $fromSalary);
        $toSalary = Carbon::createFromFormat('Y-m-d', $toSalary);
        
        $tabularBonusHolidays = $constantConfig['TabularSections']['Quy định thưởng'] ?? [];
        if (!empty($tabularBonusHolidays)) {
            foreach ($tabularBonusHolidays as $bonus) {
                if (empty($bonus['date']) && $bonus['bonus_type'] == "Thưởng sinh nhật" && !empty($employeeData['Date_of_birth'])) {
                    $day = Carbon::createFromFormat('d-F-Y', $employeeData['Date_of_birth']);
                    if ($day->gte($fromSalary) && $day->lte($toSalary)) {
                        $bonusHolidays[] = $bonus;
                    }
                }

                if (empty($bonus['date'])) continue;

                $day = Carbon::createFromFormat('d-F-Y', $bonus['date'])->format('Y-m-d');
                $day = Carbon::createFromFormat('Y-m-d', $day);
                if ($day->gte($fromSalary) && $day->lte($toSalary)) {
                    $total = $constantVals['thuong_le']['total'] ?? 0;

                    $bonusAmount = (strtolower($employeeData['contract_type']) == 'thử việc') ? $bonus['probation_amount'] : $bonus['amount'];

                    $bonus['amount_bonus_payslip'] = $bonusAmount;
                    
                    $constantVals['thuong_le']['total'] = $total + $bonusAmount;
                    $constantVals['thuong_le']['detai'][] = $bonus;
                }
            }
        }

        if (!empty($employeeData['TabularSections']['Bonus & Others'])) {
            foreach ($employeeData['TabularSections']['Bonus & Others'] as $bonus) {
     		    if (empty($bonus['Date'])) continue;

                $day = Carbon::createFromFormat('d-F-Y', $bonus['Date'])->format('Y-m-d');
                $day = Carbon::createFromFormat('Y-m-d', $day);
                if ($day->gte($fromSalary) && $day->lte($toSalary)) {
                    if ($bonus['Category'] == 'Income/Thu nhập') {
                        $bonus['bonus_type'] = 'Thưởng cá nhân';
                        $bonus['amount'] = $bonus['Amount1'];
                        $total = $constantVals['thuong_le']['total'];
                        $bonusAmount = $bonus['amount'];
                        $bonus['amount_bonus_payslip'] = $bonusAmount;
                                            
                        $constantVals['thuong_le']['total'] = $total + $bonusAmount;
                        $constantVals['thuong_le']['detai'][] = $bonus;
                    } elseif ($bonus['Category'] == 'Deduction/Giảm trừ') {
                        $bonus['amount'] = $bonus['Amount1'];
                        $total = $constantVals['thuong_le']['total'];
                        $bonusAmount = $bonus['amount'];	
                        $bonus['amount_bonus_payslip'] = $bonusAmount;
                        
                        $constantVals['khoan_tru_ca_nhan']['total'] = $total + $bonusAmount;	
                        $constantVals['khoan_tru_ca_nhan']['detai'][] = $bonus;
                    }
                }
            }
        }

        $tabularbasicSalaryAndAllowancePolicy = $constantConfig['TabularSections']['Chính sách lương cơ bản và trợ cấp'] ?? [];
        foreach ($tabularbasicSalaryAndAllowancePolicy as $row) {
            if ($employeeData['Job_Level'] == $row['Level']) {
                $constantVals['phu_cap_khac']['total'] = $row['other_allowance'];
                $constantVals['phu_cap_khac']['detai'] = $row;
                $constantVals['phu_cap_an_trua']['total'] = $row['lunch_allowance'];
                $constantVals['phu_cap_an_trua']['detai'] = $row;
                $constantVals['phu_cap_xang_xe']['total'] = $row['travel_allowance'];
                $constantVals['phu_cap_xang_xe']['detai'] = $row;
                $constantVals['phu_cap_dien_thoai']['total'] = $row['phone_allowance'];
                $constantVals['phu_cap_dien_thoai']['detai'] = $row;
            }
        }

        if (!empty($payslipExist)) {
            $basicSalaryTabular = $payslipExist['TabularSections']['Chi tiết lương cơ bản'];
            if (!empty($basicSalaryTabular)) {
                $firstBasicSalary = array_shift($basicSalaryTabular);
                $constantVals['tong_hoan_thue_tncn']['total'] = convert_decimal_length($firstBasicSalary['total_refund_tax']);
                $constantVals['truy_thu_khac']['total'] = convert_decimal_length($firstBasicSalary['other_deduction']);
                $constantVals['hoan_bhxh']['total'] = convert_decimal_length($firstBasicSalary['si_reimbursement']);
            }

            $kpiSalaryTabular = $payslipExist['TabularSections']['Chi tiết lương KPI'];
            if (!empty($kpiSalaryTabular)) {
                $firstRowKpi = array_shift($kpiSalaryTabular);
                $constantVals['truy_thu_thue_tncn']['total'] = convert_decimal_length($firstRowKpi['tax_arrears']);
            }
        }

        return [$constantConfig, $constantVals];
    }

    /**
    * mappingContantValueToFomulaValsAndKeyVals
    */
    public function mappingContantValueToFomulaValsAndKeyVals($constantVals, &$fomulaVals, &$keyWithVals)
    {
        foreach($constantVals as $key => $val) {
            foreach ($fomulaVals as &$fomula) {
                $fomula = preg_replace('/\b'.$key.'\b/u', $val['total'], $fomula);
            }

            if (!isset($keyWithVals[$key])) $keyWithVals[$key] = $val['total'];
        }
    }

    /**
    * sortFomulaSource
    */
    public function sortFomulaSource(&$fomulaSources, &$keyWithVals, $loop = false)
    {
        $loop = false;
        $notHaveMoreEval = true;

        foreach ($fomulaSources as $key => $fomula) {
            if(preg_match('/^[-+*\/()\d\.\s]+$/', $fomula)){
                try {
                    $keyWithVals[$key] = eval("return {$fomula};");
                } catch (DivisionByZeroError  $e) {
                    $keyWithVals[$key] = 0;
                }
                unset($fomulaSources[$key]);
                foreach ($fomulaSources as &$fomula2) {
                    $fomula2 = preg_replace('/\b'.$key.'\b/u', $keyWithVals[$key], $fomula2);
                }
                $notHaveMoreEval = false;
            } else {
                foreach(array_keys($fomulaSources) as $label) {
                    if (str_contains($fomula, $label)) {
                        $loop = true;
                    }
                }
            }
        }

        if ($loop && !$notHaveMoreEval) {
            $this->sortFomulaSource($fomulaSources, $keyWithVals, $loop);
        }
    }

    /**
    * generate tabularData to update in to monthly working time
    */
    public function processTabularData($formEav, $constantVals, $keyWithVals, $payslipExist)
    {
        $tabularAction = [];

        $sections = $formEav->sections;

        foreach ($sections as $section) {
            $sectionId = $section->section_id;

            if ($section->section_name == "Total Bonus/ Tổng thưởng") {
                $this->bonusTabular($tabularAction, $payslipExist, $section, $constantVals, $keyWithVals);
                continue;
            }

            if ($section->section_name == "Total Deduction/Tổng giám trừ") {
                $this->totalDeductionTabular($tabularAction, $payslipExist, $section, $constantVals, $keyWithVals);
                continue;
            }

            $action = 'add';
            $rowId  = null;

            $tabularExist = $payslipExist['TabularSections'][$section->section_name] ?? [];
            if (!empty($tabularExist)) {
                $rowId = array_key_first($tabularExist);
                $action = 'update';
            }

            if ($section->section_name == "Total Salary/Tổng lương") {
                $this->totalSalaryTabular($tabularAction, $sectionId, $action, $keyWithVals, $rowId);
                continue;
            }
            
            if ($section->section_name == "Chi tiết lương cơ bản") {
                $this->basicSalaryTabular($tabularAction, $sectionId, $action, $keyWithVals, $rowId);
                continue;
            }

            if ($section->section_name == "Chi tiết lương KPI") {
                $this->kpiSalaryTabular($tabularAction, $sectionId, $action, $keyWithVals , $rowId);
                continue;
            }
        }

        return $tabularAction;
    }

    /**
     * 'bonus_type' Các loại thưởng tương tự trường Loại thưởng trên form Cấu hình hằng số. Thưởng cá nhân: nhập trên Hồ sơ nhân viên tại bảng Bonus & Others với Danh mục = Income/Thu nhập
     * 'bonus_amount'  Hệ thống tự động cập nhật số tiền thưởng tương ứng với loại thưởng. 
     */
    public function bonusTabular(&$tabularAction, $payslipExist, $section, $constantVals, $keyWithVals)
    {
        if (!empty($constantVals['thuong_le']['detai'])) {
            foreach ($constantVals['thuong_le']['detai'] as $bonusDetail) {
                $row = [
                    'bonus_type' => $bonusDetail['bonus_type'],
                    'bonus_amount' => convert_decimal_length($bonusDetail['amount_bonus_payslip'], 0),
                ];

                $tabularAction[$section->section_id]['add'][] = $row;
            }
        }

        $tabularExist = $payslipExist['TabularSections'][$section->section_name] ?? [];
        if (!empty($tabularExist)) {
            foreach ($tabularExist as $key => $row) {
                $tabularAction[$section->section_id]['delete'][] = strval($key);
            }
        }
    }

    /**
     * 'deduction_type' 
     *      Bảo hiểm (BHXH, BHYT, BHTN): thuộc bảng Lương cơ bản
     *      Đoàn phí: thuộc bảng Lương cơ bản
     *      Truy thu khác: thuộc bảng Lương cơ bản
     *      Thuế TNCN: thuộc bảng Lương KPI
     *      Truy thu thuế TNCN: thuộc bảng Lương KPI
     *      Khoản trừ cá nhân: nhập trên Hồ sơ nhân viên tại bảng Bonus & Others với Danh mục = Deduction/Giảm trừ
     * 
     * 'deduction_amount' Hệ thống tự động cập nhật số tiền giảm trừ tương ứng với loại giảm trừ. 
     */
    public function totalDeductionTabular(&$tabularAction, $payslipExist, $section, $constantVals, $keyWithVals)
    {
        $deductionType = [
            'Bảo hiểm (BHXH, BHYT, BHTN)' => sum_number($keyWithVals['bhxh_nv'], $keyWithVals['bhyt_nv'], $keyWithVals['bhtn_nv']),
            'Đoàn phí' => $keyWithVals['doan_phi_cong_doan'],
            'Truy thu khác' => $keyWithVals['truy_thu_khac'],
            'Thuế TNCN' => $keyWithVals['thue_tncn'],
            'Truy thu thuế TNCN' => $keyWithVals['truy_thu_thue_tncn'],
            'Khoản trừ cá nhân' => $keyWithVals['khoan_tru_ca_nhan'],
        ];

        foreach ($deductionType as $key => $val) {
            $tabularAction[$section->section_id]['add'][] = [
                'deduction_type' => $key,
                'deduction_amount' => convert_decimal_length($val, 0),
            ];
        }

        $tabularExist = $payslipExist['TabularSections'][$section->section_name] ?? [];
        if (!empty($tabularExist)) {
            foreach ($tabularExist as $key => $row) {
                $tabularAction[$section->section_id]['delete'][] = strval($key);
            }
        }
    }

    /**
     * 
     * 
     */
    public function basicSalaryTabular(&$tabularAction, $sectionId,  $keyAction, $keyWithVals , $rowId = null)
    {
        $tabularRow = [
            'basic_salary' => convert_decimal_length($keyWithVals['muc_luong_co_ban'], 0),
            'insurance_salary' => convert_decimal_length($keyWithVals['luong_dong_bao_hiem'], 0),
            'official_basic_salary' => convert_decimal_length($keyWithVals['luong_chinh_thuc_theo_ngay_cong'], 0),
            'actual_basic_salary' => convert_decimal_length($keyWithVals['luong_thuc_linh_co_ban'], 0),
            'insurance_month' => convert_decimal_length($keyWithVals['so_thang_trich_bh']),
            'union_fee' => convert_decimal_length($keyWithVals['doan_phi_cong_doan'], 0),
            'si_employee' => convert_decimal_length($keyWithVals['bhxh_nv'], 0),
            'hi_employee' => convert_decimal_length($keyWithVals['bhyt_nv'], 0),
            'unemployment_fee' => convert_decimal_length($keyWithVals['bhtn_nv'], 0),
            'other_deduction' => convert_decimal_length($keyWithVals['truy_thu_khac'], 0),
            'si_reimbursement' => convert_decimal_length($keyWithVals['hoan_bhxh'], 0),
            'union_fund' => convert_decimal_length($keyWithVals['kinh_phi_cong_doan'], 0),
            'si_company' => convert_decimal_length($keyWithVals['bhxh_cong_ty'], 0),
            'accident_insurance' => convert_decimal_length($keyWithVals['bh_tai_nan_lao_dong_benh_nghe_nghiep'], 0),
            'hi_company' => convert_decimal_length($keyWithVals['bhyt_cong_ty'], 0),
            'unemployement_company' => convert_decimal_length($keyWithVals['bhtn_cong_ty'], 0),
            'total_refund_tax' => convert_decimal_length($keyWithVals['tong_hoan_thue_tncn'], 0),
        ];

        if (!is_null($rowId)) {
            $tabularAction[$sectionId][$keyAction][$rowId] = $tabularRow;
        } else {
            $tabularAction[$sectionId][$keyAction][] = $tabularRow;
        }
    }

    /**
     * 'total_salary' Hệ thống tự động cập nhật giá trị trường này bằng Lương chính thức theo ngày công (thuộc section Chi tiết lương cơ bản) + Tổng thu nhập theo KPI (thuộc section Chi tiết lương KPI)
     * 'bonus' Hệ thống tự động cập nhật bằng tổng giá trị trường Số tiền của các hàng trong bảng Khoản thưởng
     * 'allowance' Bằng Trợ cấp Xăng xe + Trợ cấp ăn trưa + Thẻ điện thoại + Làm thêm giờ + Tổng hoàn thuế TNCN (thuộc bảng Chi tiết lương KPI)
     * 'deduction' Hệ thống tự động cập nhật bằng tổng giá trị trường Số tiền của các hàng trong bảng Khoản trừ
     * 'actual_salary' Bằng Tổng lương + Tổng trợ cấp + Tổng thưởng - Tổng giảm trừ
     */
    public function totalSalaryTabular(&$tabularAction, $sectionId, $keyAction, $keyWithVals , $rowId = null)
    {
        $deductionType = [
            'Bảo hiểm (BHXH, BHYT, BHTN)' => sum_number($keyWithVals['bhxh_nv'], $keyWithVals['bhyt_nv'], $keyWithVals['bhtn_nv'],
                                                         $keyWithVals['bhxh_cong_ty'], $keyWithVals['bhyt_cong_ty'], $keyWithVals['bhtn_cong_ty']),
            'Đoàn phí' => $keyWithVals['doan_phi_cong_doan'],
            'Truy thu khác' => $keyWithVals['truy_thu_khac'],
            'Thuế TNCN' => $keyWithVals['thue_tncn'],
            'Truy thu thuế TNCN' => $keyWithVals['truy_thu_thue_tncn'],
            'Khoản trừ cá nhân' => $keyWithVals['khoan_tru_ca_nhan'],
        ];

        $totalDeduction = 0;
        foreach ($deductionType as $key => $val) {
            $totalDeduction += floatval(convert_decimal_length($val));
        }

        $totalBonus = $keyWithVals['thuong_le'];
        $totalSalary = sum_number($keyWithVals['luong_chinh_thuc_theo_ngay_cong'], $keyWithVals['tong_thu_nhap_theo_kpi']);
        $totalAllowance = sum_number($keyWithVals['phu_cap_xang_xe'], $keyWithVals['phu_cap_an_trua'], $keyWithVals['phu_cap_dien_thoai'], $keyWithVals['so_tien_lam_ngoai_gio'], $keyWithVals['tong_hoan_thue_tncn']);
        $totalActualSalary = sum_number($totalSalary, $totalBonus, $totalAllowance) - $totalDeduction;

        $tabularRow = [
            'total_salary' => convert_decimal_length($totalSalary, 0),
            'bonus' => convert_decimal_length($totalBonus, 0),
            'allowance' => convert_decimal_length($totalAllowance, 0),
            'deduction' => convert_decimal_length($totalDeduction, 0),
            'actual_salary' => convert_decimal_length($totalActualSalary, 0),
        ];

        if (!is_null($rowId)) {
            $tabularAction[$sectionId][$keyAction][$rowId] = $tabularRow;
        } else {
            $tabularAction[$sectionId][$keyAction][] = $tabularRow;
        }
    }

    /**
     * 'kpi_salary' Hệ thống tự động cập nhật giá trị trường này theo thông tin trên Hồ sơ nhân viên.
     * 'percent_KPI' Hệ thống tự động cập nhật giá trị trường này theo thông tin trên Hồ sơ nhân viên.
     * 'kpi_total_salary' Hệ thống tự động cập nhật theo form Công thức.
     * 'actual_KPI_salary' Hệ thống tự động cập nhật theo form Công thức.
     * 'meal_subsidy' Hệ thống tự động cập nhật bằng số tiền trợ cấp ăn trưa trên bản ghi Cấu hình hằng số theo cấp bậc của nhân viên. 
     * 'fuel_subsidy' Hệ thống tự động cập nhật theo form Cấu hình hằng số.
     * 'mobile_subsidy' Hệ thống tự động cập nhật theo form Cấu hình hằng số
     * 'overtime_allowance' Hệ thống tự động cập nhật theo form Công thức.
     * 'other_allowance' Hệ thống tự động cập nhật theo form Cấu hình hằng số.
     * 'standard_salary_per_hour1' Hệ thống tự động cập nhật theo form Công thức.
     * 'ot_salary' Hệ thống tự động cập nhật theo form Công thức.
     * 'taxable_overtime_amount' Hệ thống tự động cập nhật theo form Công thức.
     * 'no_tax_overtime_amount' Hệ thống tự động cập nhật theo form Công thức.
     * 'free_income_tax' Hệ thống tự động cập nhật theo form Công thức.
     * 'amount_of_deduction' Hệ thống tự động cập nhật theo form Công thức.
     * 'taxable_income' Hệ thống tự động cập nhật theo form Công thức.
     * 'personal_tax' 
     * + (Thu nhập tính thuế*Tỷ lệ thuế với mức độ 7) - 9850000 nếu Thu nhập tính thuế > Từ (VND) 
     * + (Thu nhập tính thuế*Tỷ lệ thuế với mức độ 5) - 3250000 nếu Từ (VND) < Thu nhập tính thuế < Đến (VND)
     * + (Thu nhập tính thuế*Tỷ lệ thuế với mức độ 4) - 1650000 nếu Từ (VND) < Thu nhập tính thuế < Đến (VND)
     * + (Thu nhập tính thuế*Tỷ lệ thuế với mức độ 3) - 750000 nếu Từ (VND) < Thu nhập tính thuế < Đến (VND)
     * + (Thu nhập tính thuế*Tỷ lệ thuế với mức độ 2)-250000 nếu Từ (VND) < Thu nhập tính thuế < Đến (VND)
     * + (Thu nhập tính thuế*Tỷ lệ thuế với mức độ 1) nếu Từ (VND) < Thu nhập tính thuế < Đến (VND)
     * + (Thu nhập tính thuế*Tỷ lệ thuế với mức độ 6) - 5850000 nếu Từ (VND) < Thu nhập tính thuế < Đến (VND) 
     * 'tax_arrears' Cho phép người dùng nhập trên bảng lương.
     * 'total_deduction' Hệ thống tự động cập nhật theo form Công thức.
     */
    public function kpiSalaryTabular(&$tabularAction, $sectionId,  $keyAction, $keyWithVals , $rowId = null)
    {
        $tabularRow = [
            'kpi_salary' => convert_decimal_length($keyWithVals['thu_nhap_theo_kpi'], 0),
            'percent_KPI' => convert_decimal_length($keyWithVals['phan_tram_hoan_thanh'] ?? 0),
            'kpi_total_salary' => convert_decimal_length($keyWithVals['tong_thu_nhap_theo_kpi'], 0),
            'actual_KPI_salary' => convert_decimal_length($keyWithVals['luong_thuc_linh_kpi'], 0),
            'meal_subsidy' => convert_decimal_length($keyWithVals['phu_cap_an_trua'], 0),
            'fuel_subsidy' => convert_decimal_length($keyWithVals['phu_cap_xang_xe'], 0),
            'mobile_subsidy' => convert_decimal_length($keyWithVals['phu_cap_dien_thoai'], 0),
            'overtime_allowance' => convert_decimal_length($keyWithVals['so_tien_lam_ngoai_gio']),
            'other_allowance' => convert_decimal_length($keyWithVals['phu_cap_khac']),
            'standard_salary_per_hour1' => convert_decimal_length($keyWithVals['tien_luong_tieu_chuan_gio'], 0),
            'ot_salary' => convert_decimal_length($keyWithVals['so_tien_lam_ngoai_gio'], 0),
            'taxable_overtime_amount' => convert_decimal_length($keyWithVals['so_tien_lam_ngoai_gio_chiu_thue']),
            'no_tax_overtime_amount' => convert_decimal_length($keyWithVals['so_tien_lam_ngoai_gio_mien_thue']),
            'free_income_tax' => convert_decimal_length($keyWithVals['thu_nhap_chiu_thue'], 0),
            'amount_of_deduction' => convert_decimal_length($keyWithVals['giam_tru_gia_canh'], 0),
            'taxable_income' => convert_decimal_length($keyWithVals['thu_nhap_tinh_thue'], 0),
            'personal_tax' => convert_decimal_length($keyWithVals['thue_tncn'], 0),
            'tax_arrears' => convert_decimal_length($keyWithVals['truy_thu_thue_tncn'], 0),
            'total_deduction' => convert_decimal_length($keyWithVals['tong_khau_tru'], 0),
        ];

        if (!is_null($rowId)) {
            $tabularAction[$sectionId][$keyAction][$rowId] = $tabularRow;
        } else {
            $tabularAction[$sectionId][$keyAction][] = $tabularRow;
        }
    }
}
