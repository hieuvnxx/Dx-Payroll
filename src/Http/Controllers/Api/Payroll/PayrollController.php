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
}
