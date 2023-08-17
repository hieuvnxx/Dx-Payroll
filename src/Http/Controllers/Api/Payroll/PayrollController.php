<?php


namespace Dx\Payroll\Http\Controllers\Api\Payroll;

use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;

/**
 * insert database zoho form
 */
class PayrollController extends BaseController
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

    protected function getAllDataFormLinkName($formLinkName)
    {
        $response = new Collection();

        $offset = 0;
        $limit  = 1000;
        while (true) {
            $datas = $this->zohoRecord->getRecords($formLinkName, $offset, $limit);
            if (empty($datas)) {
                break;
            }

            $response = $response->merge(collect($datas));

            $offset += $limit;
        }

        return $response;
    }

    protected function getPersonalIncomeTax(&$keyWithVals, $constantConfig)
    {
        $keyWithVals['thue_tncn'] = 0;
        if (empty($keyWithVals['thu_nhap_tinh_thue']) || empty($constantConfig['TabularSections']['Quy định tính thuế TNCN'])) return;

        $incomeTaxRules = $constantConfig['TabularSections']['Quy định tính thuế TNCN'];

        $salaryWithIncomeTax = $keyWithVals['thu_nhap_tinh_thue'];

        foreach ($incomeTaxRules as $rule) {
            $from = $rule['From_VND'];
            $to = $rule['To_VND'];
            $level = $rule['Level1'];
            $percent = $rule['Tax_Rate1'];
            if ($from > 0 && $to == 0 && $from < $salaryWithIncomeTax) {
                $keyWithVals['thue_tncn'] = $this->calculateIncomeTax($salaryWithIncomeTax, $level, $percent);
            }

            if ($from < $salaryWithIncomeTax && $salaryWithIncomeTax < $to) {
                $keyWithVals['thue_tncn'] = $this->calculateIncomeTax($salaryWithIncomeTax, $level, $percent);
            }
        }
    }

    protected function calculateIncomeTax($salaryWithIncomeTax, $level, $percent)
    {
        $levels = [
            7 => 9850000,
            6 => 5850000,
            5 => 3250000,
            4 => 1650000,
            3 => 750000,
            2 => 250000,
        ];

        if (isset($levels[$level])) {
            return $salaryWithIncomeTax * $percent/100 - $levels[$level];
        }

        return $salaryWithIncomeTax * $percent/100;
    }
}
