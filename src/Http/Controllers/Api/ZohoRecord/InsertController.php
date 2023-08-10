<?php


namespace Dx\Payroll\Http\Controllers\Api\ZohoRecord;

use Dx\Payroll\Http\Requests\ApiInsertZohoRecord;
use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Illuminate\Support\Facades\Log;

/**
 * insert database zoho form
 */
class InsertController extends BaseController
{
    protected $zohoLib;
    protected $zohoForm;
    protected $zohoRecord;

    public function __construct(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord, ApiInsertZohoRecord $request)
    {
        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $this->zohoForm = $zohoForm;
        $this->zohoRecord = $zohoRecord;
    }
   
    public function index(ApiInsertZohoRecord $request)
    {
        $zohoID = $request->Zoho_ID;
        $formLinkName = $request->formLinkName;

        $response = $this->zohoLib->getRecordByID($formLinkName, $zohoID);
        if (isset($response['errors'])) {
            Log::channel('dx')->error(self::class . ' ::: getRecordByID ::: ' , $response);
            return $this->sendError($request, self::class . ' ::: getRecordByID', $response);
        }

        $response = collect($response);
    }
}
