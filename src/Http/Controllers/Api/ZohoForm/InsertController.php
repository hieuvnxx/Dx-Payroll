<?php


namespace Dx\Payroll\Http\Controllers\Api\ZohoForm;

use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Illuminate\Support\Facades\Request;

/**
 * insert database zoho form
 */
class InsertController extends BaseController
{
    protected $zohoLib;
    protected $zohoForm;
    protected $zohoRecord;

    public function __contruct(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord)
    {
        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $this->zohoForm = $zohoForm;
        $this->zohoRecord = $zohoRecord;
    }
   
    public function index(Request $request)
    {
        dd($request);
    }
}
