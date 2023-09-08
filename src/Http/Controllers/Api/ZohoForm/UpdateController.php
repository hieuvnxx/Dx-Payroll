<?php


namespace Dx\Payroll\Http\Controllers\Api\ZohoForm;

use Dx\Payroll\Http\Controllers\Api\BaseController;
use Dx\Payroll\Http\Requests\ApiUpdateZohoForm;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Models\ZohoRecordField;
use Dx\Payroll\Models\ZohoSection;
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
    public function index(ApiUpdateZohoForm $request)
    {
        Log::channel('dx')->info(self::class .' ::: BEGIN index ::: ' , $request->all());

        $formLinkName = $request->formLinkName;

        $zohoForm = $this->zohoForm->where('form_link_name', $formLinkName)->first();
        if (is_null($zohoForm)) {
            return $this->sendError($request, 'formLinkname not found');
        }

        $arrComp = $this->zohoLib->getSectionForm($formLinkName, 2, false);
        if (!isset($arrComp['response']['result']) || empty($arrComp['response']['result'])) {
            return $this->sendError($request, 'Something error. getSectionForm ::: ', $arrComp);
        }
        
        $fieldDetails = [];
        try {
            DB::beginTransaction();
            foreach ($arrComp['response']['result'] as $data) {
                if (!empty($data['tabularSections'])) {
                    foreach ($data['tabularSections'] as $sectionData) {
                        foreach ($sectionData as $key => $dataSection) {
                            if($key != 'sectionId'){
                                $section = ZohoSection::updateOrCreate([
                                        'form_id' => $zohoForm->id,
                                        'section_id' => $sectionData['sectionId'],
                                        'section_label' => $key
                                    ], [
                                        'section_id' => $sectionData['sectionId'],
                                    ]);

                                foreach ($dataSection as $sectionField) {
                                    $responseUpdateOrCreate = ZohoRecordField::updateOrCreate([
                                            'form_id' => $zohoForm->id,
                                            'section_id' => $section->id,
                                            'label_name' => $sectionField['labelname'],
                                    ], [
                                        'form_id' => $zohoForm->id,
                                        'section_id' => $section->id,
                                        'display_name' => $sectionField['displayname'],
                                        'label_name' => $sectionField['labelname'],
                                        'comp_type' => $sectionField['comptype'],
                                        'autofillvalue' => $sectionField['autofillvalue'],
                                        'is_mandatory' => $sectionField['ismandatory'],
                                        'options' => null,
                                        'decimal_length' => $sectionField['decimalLength'] ?? null,
                                        'max_length' => $sectionField['maxLength'] ?? null,
                                    ]);

                                    $fieldDetails[] = $responseUpdateOrCreate->id;
                                }
                            }
                        }
                    }
                    continue;
                }

                $responseUpdateOrCreate = ZohoRecordField::updateOrCreate([
                    'form_id' => $zohoForm->id,
                    'section_id' => 0,
                    'label_name' => $data['labelname'],
                ], [
                    'form_id' => $zohoForm->id,
                    'section_id' => 0,
                    'display_name' => $data['displayname'],
                    'label_name' => $data['labelname'],
                    'comp_type' => $data['comptype'],
                    'autofillvalue' => $data['autofillvalue'],
                    'is_mandatory' => $data['ismandatory'],
                    'options' => null,
                    'decimal_length' => $data['decimalLength'] ?? null,
                    'max_length' => $data['maxLength'] ?? null,
                ]);

                $fieldDetails[] = $responseUpdateOrCreate->id;
            }

            ZohoRecordField::where('form_id', $zohoForm->id)->whereNotIn('id', $fieldDetails)->delete();
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return $this->sendError($request, 'Something error. Exception ::: ', $e->getMessage());
        }

        return $this->sendResponse($request, 'Successfully.', $fieldDetails);
    }
}
