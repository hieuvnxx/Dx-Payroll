<?php


namespace Dx\Payroll\Http\Controllers\Api\ZohoRecord;

use Dx\Payroll\Http\Requests\ApiInsertZohoRecord;
use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * insert database zoho form
 */
class DeleteController extends BaseController
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
     * handle insert record to database EAV
     */
    public function index(ApiInsertZohoRecord $request)
    {
        
    }

    public function insertZohoRecordValue($attributes, $zohoRecord, $zohoData, $rowId = 0)
    {
        
    }
}
