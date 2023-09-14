<?php


namespace Dx\Payroll\Http\Controllers\Api\ZohoRecord;

use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Http\Requests\ApiMassInsertZohoRecord;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Jobs\ProcessSyncDataFormLinkName;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * insert database zoho form
 */
class MassInsertController extends BaseController
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
    public function index(ApiMassInsertZohoRecord $request)
    {
        Log::channel('dx')->info(self::class .' ::: BEGIN index ::: ' , $request->all());

        $formLinkName = $request->formLinkName;

        $cloneZohoFormInterface = clone $this->zohoForm;
        $zohoForm = $cloneZohoFormInterface->with(['sections', 'attributes', 'sections.attributes'])->where('form_link_name', $formLinkName)->first();
        if (is_null($zohoForm)) {
            throw new \ErrorException('Not found '.$formLinkName.' in database');
        }

        Cache::forget($formLinkName);

        ProcessSyncDataFormLinkName::dispatch($formLinkName, $zohoForm);
        
        return $this->sendResponse($request, 'Successfully.');
    }
}
