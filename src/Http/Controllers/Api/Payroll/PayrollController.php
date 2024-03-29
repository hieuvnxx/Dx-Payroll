<?php


namespace Dx\Payroll\Http\Controllers\Api\Payroll;

use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;

/**
 * insert database zoho form
 */
class PayrollController extends BaseController
{
    public function getAllDataFormLinkName($formLinkName, ZohoRecordInterface $zohoRecord)
    {
        if (Cache::has($formLinkName)) {
            return Cache::get($formLinkName);
        }

        $offset = 0;
        $limit  = 1000;
        $response = Cache::rememberForever($formLinkName, function () use ($zohoRecord, $formLinkName, $offset, $limit) {
            $response = new Collection();

            while (true) {
                $datas = $zohoRecord->getRecords($formLinkName, $offset, $limit);
                if (empty($datas)) {
                    break;
                }
    
                $response = $response->merge(collect($datas));
    
                $offset += $limit;
            }
    
            return $response->keyBy('Zoho_ID')->toArray();
        });

        return $response;
    }

    public function getPersonalIncomeTax(&$keyWithVals, $constantConfig)
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

    public function calculateIncomeTax($salaryWithIncomeTax, $level, $percent)
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
