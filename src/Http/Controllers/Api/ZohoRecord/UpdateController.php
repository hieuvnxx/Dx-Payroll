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
class UpdateController extends BaseController
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
     * handle update record to database EAV
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

            $zohoForm = $this->zohoForm->with(['sections', 'attributes', 'sections.attributes'])->where('form_link_name', $formLinkName)->first();
            if (is_null($zohoForm)) {
                return $this->sendError($request, ' ::: $this->zohoForm ::: Not found form in database');
            }

            $zohoRecord = $this->zohoRecord->where('form_id', $zohoForm->id)
                                           ->where('zoho_id', $zohoID)
                                           ->first();

            if (is_null($zohoRecord)) {
                Log::channel('dx')->info(self::class .' ::: ERROR $zohoRecord empty ');
                return $this->sendError($request, 'zohoRecord empty');
            }

            $zohoFormSections = $zohoForm->sections->keyBy('section_name');
            if (!empty($responseDataRecord['tabularSections'])) {
                foreach ($responseDataRecord['tabularSections'] as $tabularName => $values) {
                    if (!isset($zohoFormSections[$tabularName]) || empty($values[0])) continue;

                    foreach ($values as $value) {
                        $this->updateZohoRecordValue($zohoFormSections[$tabularName]->attributes->keyBy('field_label'), $zohoRecord, $value, $value['tabular.ROWID']);
                    }
                }
                unset($responseDataRecord['tabularSections']);
            }

            $this->updateZohoRecordValue($zohoForm->attributes->keyBy('field_label'), $zohoRecord, $responseDataRecord);

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

    public function updateZohoRecordValue($attributes, $zohoRecord, $zohoData, $rowId = 0)
    {
        $arrayKeys = array_keys($zohoData);

        foreach ($arrayKeys as $fieldLabel) {
            if (isset($attributes[$fieldLabel])) {
                if ($attributes[$fieldLabel]->type == "Lookup") {
                    $value = $zohoData[$fieldLabel.'.ID'] ?? $zohoData[$fieldLabel.'.id'] ?? $zohoData[$fieldLabel];
                } else {
                    $value = $zohoData[$fieldLabel];
                }

                $this->zohoRecordValue->where('record_id', $zohoRecord->id)
                ->where('field_id', $attributes[$fieldLabel]->id)
                ->where('row_id', $rowId)
                ->update(['value' => $value]);
            }
        }
    }
}
