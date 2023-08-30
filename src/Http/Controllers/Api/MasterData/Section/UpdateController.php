<?php


namespace Dx\Payroll\Http\Controllers\Api\MasterData\Section;

use Dx\Payroll\Http\Requests\ApiInsertZohoRecord;
use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Dx\Payroll\Repositories\ZohoSectionInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * insert database zoho form
 */
class UpdateController extends BaseController
{
    protected $zohoLib;
    protected $zohoForm;
    protected $zohoSection;

    public function __construct(ZohoFormInterface $zohoForm, ZohoSectionInterface $zohoSection)
    {
        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $this->zohoForm = $zohoForm;
        $this->zohoSection = $zohoSection;
    }
   
    /**
     * handle update record to database EAV
     */
    public function index(ApiInsertZohoRecord $request)
    {
        Log::channel('dx')->info(self::class .' ::: BEGIN index ::: ' , $request->all());

        $formLinkName = $request->formLinkName;
        $sectionLabel = $request->Section_Label;
        $sectionName  = $request->Section_Name;

        $zohoForm = $this->zohoForm->where('form_link_name', $formLinkName)->first();
        if (is_null($zohoForm)) {
            return $this->sendError($request, ' ::: $this->zohoForm ::: Not found form in database');
        }

        $this->zohoSection->where('form_id', $zohoForm->id)
                          ->where('section_label', $sectionLabel)
                          ->update(['section_name' => $sectionName]);

        return $this->sendResponse($request, 'Successfully.');
    }
}
