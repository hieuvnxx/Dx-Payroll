<?php


namespace Dx\Payroll\Http\Controllers\Api\Payroll;

use Carbon\Carbon;
use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Http\Requests\ApiOvertimeUpdateData;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;

/**
 * insert database zoho form
 */
class OvertimeController extends BaseController
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

    public function updateData(ApiOvertimeUpdateData $request)
    {
        $zohoId = $request->Zoho_ID;
        $overtimeRequestFormLinkName = Env::get('PAYROLL_OT_REQUEST_FORM_LINK_NAME', null);

        $overtimeResponseByZohoId = $this->zohoLib->getRecordByID($overtimeRequestFormLinkName, $zohoId);
        $overtimeDate  = $overtimeResponseByZohoId['date'];
        $overtimeStartHour = $overtimeResponseByZohoId['from'];
        $overtimeendHour = $overtimeResponseByZohoId['to'];

        if (!$overtimeDate || !$overtimeStartHour || !$overtimeendHour) {
            return $this->sendError($request, 'Overtime request missing Date, From Time, End Time.', $overtimeResponseByZohoId);
        }

        $overTimeFromTime = "$overtimeDate $overtimeStartHour";
        $overTimeEndTime = "$overtimeDate $overtimeendHour";
        $overTimeFromTime = Carbon::parse($overTimeFromTime);
        $overTimeEndTime = Carbon::parse($overTimeEndTime);
        $diffMinutes = $overTimeFromTime->diffInMinutes($overTimeEndTime);

        $diffHour = convert_decimal_length($diffMinutes/60, 1);

        $inputData = [
            'hour' => $diffHour
        ];
        
        $rspUpdate = $this->zohoLib->updateRecord($overtimeRequestFormLinkName, $inputData, [], $zohoId);
        if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
            return $this->sendError($request, 'Something error. Can not update overtime request with ZOHO ID : '. $zohoId, [$inputData, $rspUpdate]);
        }

        return $this->sendResponse($request, 'Successfully.');
    }
}
