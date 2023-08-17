<?php


namespace Dx\Payroll\Http\Controllers\Api\Payroll;

use Carbon\Carbon;
use DivisionByZeroError;
use Dx\Payroll\Http\Controllers\Api\Payroll\PayrollController;
use Dx\Payroll\Http\Requests\ApiPayslipByCode;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Exception;
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
        $payslipFormLinkName = Env::get('PAYROLL_PAYSLIP_FORM_LINK_NAME');

        $empCode = $request->code;
        $month   = $request->month;
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;

        $masterDataFormCollect = $this->getAllDataFormLinkName($formMasterDataFormLinkName);
        $salaryFactorCollect = $this->getAllDataFormLinkName($salaryFactorFormLinkName);
        $formulaSourceCollect = $this->getAllDataFormLinkName($formulaSourceFormLinkName);

        $cacheDataForm = [];
        /** formEav*/
        $formEav = $this->zohoForm->has('attributes', 'sections', 'sections.attributes')->with('attributes', 'sections', 'sections.attributes')->where('form_link_name', $payslipFormLinkName)->first();
        if (is_null($formEav)) {
            return $this->sendError($request, 'Something error with monthly working time form in database.');
        }

        /** employee information */
        $employeeData = $this->zohoRecord->getRecords($employeeFormLinkName, 0, 200, [$employeeIdNumberFieldLabel => $empCode])[0];
        $cacheDataForm[$employeeFormLinkName] = $employeeData;

        /** fetch data working time for employee */
        $monthlyWorkingsByCode = $this->zohoRecord->getRecords($monthlyWorkingTimeFormLinkName, 0, 200, ['code' => $code, 'salary_period' => $monthly]);
        $existMonthlyWorkingTime = !empty($monthlyWorkingsByCode) ? $monthlyWorkingsByCode[0] : [];
        $standardWorkingDay = $existMonthlyWorkingTime['standard_working_day'] ?? 0;
        $standardWorkingDayProbation = $existMonthlyWorkingTime['standard_working_day_probation'] ?? 0;

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
                    continue;
                }
            }

            return [ $factor['abbreviation'] => $fomulaString];
        })->values()->collapse()->all();

        $constantVals = $this->mappingConstantWithKeyValue($month, $employeeData);
        $this->mappingContantValueToFomulaValsAndKeyVals($constantVals, $fomulaVals, $keyWithVals);
        $this->sortFomulaSource($fomulaVals, $keyWithVals);

        $inputData = [];
        $inputData['employee1']                      = $employeeData['Zoho_ID'];
        $inputData['salary_period']                  = $monthly;
        $inputData['code']                           = $code;
        $inputData['standard_working_day']           = intval($standardWorkingDay);
        $inputData['standard_working_day_probation'] = intval($standardWorkingDayProbation);


        /* check if exist record */
        $payslipExists = $this->zohoRecord->getRecords($payslipFormLinkName, 0, 1, [
            'code' => $code,
            'salary_period'=> $monthly
        ]);
        $payslipExist = isset($payslipExists[0]) ? $payslipExists[0] : [];


        $payslipLogDetails = [];

        $tabularData = $this->processTabularData($formEav, $fomulaVals, $keyWithVals, $payslipExist);
        if (!empty($payslipExist)) {
            $payslipZohoId = $payslipExist['Zoho_ID'];
            $responseUpdatePayslip = $this->zohoLib->updateRecord($payslipFormLinkName, $inputData, $tabularData, $payslipZohoId);
            $payslipLogDetails[] = $responseUpdatePayslip;
            if (!isset($responseUpdatePayslip['result']) || !isset($responseUpdatePayslip['result']['pkId'])) {
                return $this->sendError($request, 'Something error. Can not update exist payslip with zoho id : '. $payslipZohoId, [$responseUpdatePayslip, $inputData, $tabularData]);
            }
        } else {
            $rspInsert = $this->zohoLib->insertRecord($payslipFormLinkName, $inputData, 'yyyy-MM-dd');
            $payslipLogDetails[] = $rspInsert;
            if (!isset($rspInsert['result']) || !isset($rspInsert['result']['pkId'])) {
                return $this->sendError($request, 'Something error. Can not insert new record payslip in to zoho.', $rspInsert);
            }
    
            $zohoId = $rspInsert['result']['pkId'];
            $rspUpdate = $this->zohoLib->updateRecord($payslipFormLinkName, $inputData, $tabularData, $zohoId, 'yyyy-MM-dd');
            $payslipLogDetails[] = $rspUpdate;
            if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
                return $this->sendError($request, 'Something error. Can not update payslip with zoho id : '. $zohoId, $rspUpdate);
            }
        }

        return $this->sendResponse($request, 'Successfully.', $payslipLogDetails);
    }

    private function replaceSystemDataToFactor(&$cacheDataForm, $formLinkName, $fieldLabel, $searchParams = [], $zohoId = null)
    {
        if (!empty($searchParams) && !empty($cacheDataForm[$formLinkName.$fieldLabel])) {
            return $cacheDataForm[$formLinkName.$fieldLabel][$fieldLabel] ?? '';
        } elseif(!empty($cacheDataForm[$formLinkName])) {
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
    * mappingConstantWithKeyValue
    */
    private function mappingConstantWithKeyValue($month, $employeeData)
    {
        $response = [];

        $constantConfigFormLinkName = Env::get('PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME');
        $constantConfigs = $this->zohoRecord->getRecords($constantConfigFormLinkName);
        $constantConfig = $constantConfigs[0];

        list($fromSalary, $toSalary) = payroll_range_date($month, $constantConfig['from_date'], $constantConfig['to_date']);
        $fromSalary = Carbon::createFromFormat('Y-m-d', $fromSalary);
        $toSalary = Carbon::createFromFormat('Y-m-d', $toSalary);

        $bonusHolidays = [];
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
                    $bonusHolidays[] = $bonus;
                }
            }
        }

        if (!empty($bonusHolidays)) {
            foreach ($bonusHolidays as $bonus) {
                $total = $response['thuong_le']['total'] ?? 0;
                $bonusAmount = (strtolower($employeeData['contract_type']) != 'thử việc') ? $bonus['probation_amount'] : $bonus['amount'];
                
                $response['thuong_le']['total'] = $total + $bonusAmount;
                $response['thuong_le']['detai'][] = $bonus;
            }
        }

        /** hard code */
        $response['tong_hoan_thue_tncn']['total'] = 0;
        $response['thue_tncn']['total'] = 0;
        $response['truy_thu_thue_tncn']['total'] = 0;
        $response['truy_thu_khac']['total'] = 0;
        $response['hoan_bhxh']['total'] = 0;
        $response['tam_ung']['total'] = 0;

        return $response;
    }

    /**
    * mappingConstantWithKeyValue
    */
    private function mappingContantValueToFomulaValsAndKeyVals($constantVals, &$fomulaVals, &$keyWithVals)
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
    private function sortFomulaSource(&$fomulaSources, &$keyWithVals, $loop = false)
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
    private function processTabularData($formEav, $fomulaVals, $keyWithVals, $payslipExist)
    {
        $tabularAction = [];

        $sections = $formEav->sections;

        foreach ($sections as $section) {
            $tabularExist = $payslipExist['TabularSections'][$section->section_name] ?? [];
            if (!empty($tabularExist)) {
                if ($section->section_name == "Chi tiết lương cơ bản") {
                    $this->basicSalaryTabular($tabularAction, $section->section_id, 'update', $keyWithVals , array_key_first($tabularExist));
                    continue;
                }

                if ($section->section_name == "Chi tiết lương KPI") {
                    $this->kpiSalaryTabular($tabularAction, $section->section_id, 'update', $keyWithVals , array_key_first($tabularExist));
                    continue;
                }

            } else {
                if ($section->section_name == "Chi tiết lương cơ bản") {
                    $this->basicSalaryTabular($tabularAction, $section->section_id, 'add', $keyWithVals);
                    continue;
                }

                if ($section->section_name == "Chi tiết lương KPI") {
                    $this->kpiSalaryTabular($tabularAction, $section->section_id, 'add', $keyWithVals);
                    continue;
                }
            }
            
            // if ($section->section_name == "Total Salary/Tổng lương") {

            // }

            // if ($section->section_name == "Total Bonus/ Tổng thưởng") {
                
            // }

            // if ($section->section_name == "Total Deduction/Tổng giám trừ") {
                
            // }
        }

        return $tabularAction;
    }

    private function basicSalaryTabular(&$tabularAction, $sectionId,  $keyAction, $keyWithVals , $rowId = null)
    {
        $tabularRow = [
            'basic_salary' => convert_decimal_length($keyWithVals['muc_luong_co_ban'], 0) ?? '',
            'insurance_salary' => $keyWithVals['luong_dong_bao_hiem'] ?? '', // note
            'official_basic_salary' => convert_decimal_length($keyWithVals['luong_chinh_thuc_theo_ngay_cong'], 0) ?? '',
            'actual_basic_salary' => convert_decimal_length($keyWithVals['luong_thuc_linh_co_ban'], 0) ?? '',
            'insurance_month' => $keyWithVals['so_thang_trich_bh'] ?? '',
            'union_fee' => $keyWithVals['doan_phi_cong_doan'] ?? '',
            'si_employee' => $keyWithVals['bhxh_nv'] ?? '',
            'hi_employee' => $keyWithVals['bhyt_nv'] ?? '',
            'unemployment_fee' => $keyWithVals['bhtn_nv'] ?? '',
            'other_deduction' => $keyWithVals['truy_thu_khac'] ?? '',
            'si_reimbursement' => $keyWithVals['hoan_bhxh'] ?? '',
            'union_fund' => $keyWithVals['kinh_phi_cong_doan'] ?? '',
            'si_company' => $keyWithVals['bhxh_cong_ty'] ?? '',
            'accident_insurance' => $keyWithVals['bh_tai_nan_lao_dong_benh_nghe_nghiep'] ?? '',
            'hi_company' => $keyWithVals['bhyt_cong_ty'] ?? '',
            'unemployement_company' => $keyWithVals['bhtn_cong_ty'] ?? '',
            'total_refund_tax' => $keyWithVals['tong_hoan_thue_tncn'] ?? '',
        ];

        if (!is_null($rowId)) {
            $tabularAction[$sectionId][$keyAction][$rowId] = $tabularRow;
        } else {
            $tabularAction[$sectionId][$keyAction][] = $tabularRow;
        }
    }

    private function kpiSalaryTabular(&$tabularAction, $sectionId,  $keyAction, $keyWithVals , $rowId = null)
    {
        $tabularRow = [
            'kpi_salary' => $keyWithVals['thu_nhap_theo_kpi'] ?? '',
            'percent_KPI' => $keyWithVals['percent_KPI'] ?? '', // note
            'kpi_total_salary' => $keyWithVals['tong_thu_nhap_theo_kpi'] ?? '',
            'actual_KPI_salary' => $keyWithVals['actual_KPI_salary'] ?? '',
            'meal_subsidy' => $keyWithVals['phu_cap_an_trua'] ?? '',
            'fuel_subsidy' => $keyWithVals['phu_cap_xang_xe'] ?? '',
            'mobile_subsidy' => $keyWithVals['phu_cap_dien_thoai'] ?? '',
            'overtime_allowance' => $keyWithVals['so_tien_lam_ngoai_gio'] ?? '',
        ];

        if (!is_null($rowId)) {
            $tabularAction[$sectionId][$keyAction][$rowId] = $tabularRow;
        } else {
            $tabularAction[$sectionId][$keyAction][] = $tabularRow;
        }
    }

}
