<?php


namespace Dx\Payroll\Http\Controllers\Api\ZohoRecord;

use Carbon\Carbon;
use DateTime;
use Dx\Payroll\Http\Requests\ApiInsertZohoRecord;
use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * insert database zoho form
 */
class InsertController extends BaseController
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
        Log::channel('dx')->info(self::class .' ::: BEGIN index ::: ' , $request->all());

        $zohoID = $request->Zoho_ID;
        $formLinkName = $request->formLinkName;

        $responseDataRecord = $this->zohoLib->getRecordByID($formLinkName, $zohoID);
        if (isset($responseDataRecord['errors'])) {
            return $this->sendError($request, 'getRecordByID', $responseDataRecord);
        }

        try {
            DB::beginTransaction();

            Cache::forget($formLinkName);

            $zohoForm = $this->zohoForm->with(['sections', 'attributes', 'sections.attributes'])->where('form_link_name', $formLinkName)->first();
            if (is_null($zohoForm)) {
                return $this->sendError($request, ' ::: $this->zohoForm ::: Not found form in database');
            }

            $zohoRecord = $this->zohoRecord->create(['form_id' => $zohoForm->id, 'zoho_id' => $zohoID]);
            
            $zohoFormSections = $zohoForm->sections->keyBy('section_name');
            if (!empty($responseDataRecord['tabularSections'])) {
                foreach ($responseDataRecord['tabularSections'] as $tabularName => $values) {
                    if (!isset($zohoFormSections[$tabularName]) || empty($values[0])) continue;

                    foreach ($values as $value) {
                        $this->insertZohoRecordValue($zohoFormSections[$tabularName]->attributes->keyBy('label_name'), $zohoRecord, $value, $value['tabular.ROWID']);
                    }
                }
                unset($responseDataRecord['tabularSections']);
            }

            $this->insertZohoRecordValue($zohoForm->attributes->keyBy('label_name'), $zohoRecord, $responseDataRecord);

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

    public function insertZohoRecordValue($attributes, $zohoRecord, $zohoData, $rowId = 0)
    {
        $arrayKeys = array_keys($zohoData);

        foreach ($arrayKeys as $fieldLabel) {
            if (isset($attributes[$fieldLabel])) {
                $dateFormat = null;
                $dateTimeFormat = null;
                if(($attributes[$fieldLabel]['comp_type'] == 'Datetime' || $attributes[$fieldLabel]['comp_type'] == 'Date') && !empty($zohoData[$fieldLabel])) {
                    $dateTime = new DateTime($zohoData[$fieldLabel]);
                    $dateFormat = Carbon::parse($dateTime)->format('Y-m-d');
                    $dateTimeFormat = Carbon::parse($dateTime)->format('Y-m-d H:i:s');
                }

                $this->zohoRecordValue->create([
                    'record_id' => $zohoRecord->id,
                    'field_id' => $attributes[$fieldLabel]->id,
                    'row_id' => $rowId,
                    'value' => $zohoData[$fieldLabel],
                    'date' => $dateFormat,
                    'date_time' => $dateTimeFormat,
                ]);
            }
        }
    }
}
