<?php

namespace Dx\Payroll\Http\Controllers;

class PayslipController extends BaseController
{
    public function processPayslip($arrInput){

        $forms    = $this->records->getRecords($arrInput['config_payroll']['form_master_form_name']);
        $factors    = $this->records->getRecords($arrInput['config_payroll']['factor_master_form_name']);
        $formulas   = $this->records->getRecords($arrInput['config_payroll']['fomular_form_name']);

        $sourceFormular = [];
        $sourceValue = [];
        foreach ($factors as $factor){
            if($factor['type'] == 'Tính theo công thức'){
                foreach ($formulas as $formula){
                    if($formula['field'] == $factor['Zoho_ID'] && isset($formula['formula'])){
                        $sourceFormular[$factor['abbreviation']] = $formula['formula'];
                    }
                }
            }
            if($factor['type'] == 'Có sẵn trên hệ thống'){
                foreach ($forms as $form){
                    if($factor['form_name'] == $form['Zoho_ID']){
                        $sourceValue[] = array_merge($form, $factor);
                    };
                }
            };
        }
        $arrMath = [];
        $arrExpression = [];
        $math = ['+', '-', '*', '/', '(', ')'];
        foreach ($sourceFormular as $res => $fomularRes){
            $arrExpression = explode('|', str_replace($math, '|', $fomularRes));
            foreach ($arrExpression as $expression){
                if(!empty($expression)){
                    if(!in_array($expression, $arrMath)){
                        $arrMath[] = $expression;
                    }
                    foreach ($sourceFormular as $req => $fomularReq){
                        if($expression == $req){
                            $sourceFormular[$res] = str_replace($req, '('.$fomularReq.')', $fomularRes);
                        }
                    }
                }
            }
        }
        sort($arrMath);

        dd($arrMath, $sourceValue, $sourceFormular);

    }
}
