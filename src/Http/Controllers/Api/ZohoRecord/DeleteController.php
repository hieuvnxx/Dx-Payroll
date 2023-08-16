<?php


namespace Dx\Payroll\Http\Controllers\Api\ZohoRecord;

use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Http\Requests\ApiDeleteZohoRecord;
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
    public function index(ApiDeleteZohoRecord $request)
    {
        Log::channel('dx')->info(self::class .' ::: BEGIN index ::: ' , $request->all());

        $zohoID = $request->Zoho_ID;
        $formLinkName = $request->formLinkName;

        try {
            DB::beginTransaction();

            $zohoForm = $this->zohoForm->where('form_link_name', $formLinkName)->first();
            if (is_null($zohoForm)) {
                return $this->sendError($request, ' ::: $this->zohoForm ::: Not found form in database');
            }

            $zohoRecord = $this->zohoRecord->where(['form_id' => $zohoForm->id, 'zoho_id' => $zohoID])->first();
            if (is_null($zohoRecord)) {
                return $this->sendError($request, ' ::: $this->zohoRecord ::: Not found record in database . ZohoID: ' . $zohoID);
            }

            $this->zohoRecordValue->where('record_id', $zohoRecord->id)->delete();
            $zohoRecord->delete();
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return $this->sendError($request, 'Exception index  ::: ' . $e->getMessage(), [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
        }
        
        return $this->sendResponse($request, 'Successfully.');
    }
}
