<?php

namespace Dx\Payroll\Jobs\MasterData;

use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Models\ZohoForm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;

class ProcessSyncFormSectionFieldToZoho implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $zohoLib;

    protected $zohoForm;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ZohoForm $zohoForm)
    {
        $this->zohoForm = $zohoForm;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->zohoLib  = ZohoPeopleIntegration::getInstance();

        $masterDataFormFormLinkName = Env::get('MASTER_DATA_FORM_FORM_LINK_NAME');
        $masterDataSectionFormLinkName = Env::get('MASTER_DATA_FORM_SECTION_LINK_NAME');
        $masterDataFieldFormLinkName = Env::get('MASTER_DATA_FORM_FIELD_LINK_NAME');

        $generalFields = $this->zohoForm->attributes;
        $sections = $this->zohoForm->sections;

        $formDisplayName = $this->zohoForm->display_name;
        $formLinkName = $this->zohoForm->form_link_name;

        $inputDataForm = [
            'Display_Name' => $formDisplayName,
            'Form_Link_Name' => $formLinkName,
            'Is_Custom' => $this->zohoForm->is_custom ? "true" : "false",
            'Is_Visible' => $this->zohoForm->is_visible ? "true" : "false",
            'View_ID' => $this->zohoForm->view_id,
            'View_Name' => $this->zohoForm->view_name,
        ];

        $rspMasterDataForm = $this->zohoLib->insertRecord($masterDataFormFormLinkName, $inputDataForm);
        if (!isset($rspMasterDataForm['result']) || !isset($rspMasterDataForm['result']['pkId'])) {
            Log::channel('dx')->error('Something error. Can not insert master data form.', [$inputDataForm, $rspMasterDataForm]);
            throw new \ErrorException('Something error. Can not insert master data form.'. json_encode([$inputDataForm, $rspMasterDataForm]));
        }

        $masterDataFormZohoId = $rspMasterDataForm['result']['pkId'];

        foreach ($generalFields as $field) {
            $fieldLabelName = $field->label_name;
            $inputDataField = [
                'Belong_To_Form' => $masterDataFormZohoId,
                'Form_Name' => $formDisplayName,
                'Display_Name' => $field->display_name,
                'Label_Name' => $fieldLabelName,
                'Comp_Type' => $field->comp_type,
                'Auto_Fill_Value' => $field->autofillvalue,
                'Is_Mandatory' => $field->is_mandatory ? "true" : "false",
                'Decimal_Length' => $field->decimal_length ?? '',
                'Max_Length' => $field->max_length ?? '',
            ];

            $rspMasterDataField = $this->zohoLib->insertRecord($masterDataFieldFormLinkName, $inputDataField);
            if (!isset($rspMasterDataField['result']) || !isset($rspMasterDataField['result']['pkId'])) {
                Log::channel('dx')->error('Something error. Can not insert master data field.', [$inputDataField, $rspMasterDataField]);
                throw new \ErrorException('Something error. Can not insert master data field.'. json_encode([$inputDataField, $rspMasterDataField]));
            }
            Log::channel('dx')->info('insert zoho master data field . Form : '. $this->zohoForm->form_link_name . ' Section :' . ' Field : ' . $fieldLabelName);
        }

        if ($sections->isNotEmpty()) {
            foreach ($sections as $section) {
                $sectionName = $section->section_name;
                $sectionLabel = $section->section_label;
                $sectionFields = $section->attributes;

                $inputDataSection = [
                    'Belong_To_Form' => $masterDataFormZohoId,
                    'Form_Name' => $formDisplayName,
                    'Section_ID' => $section->section_id,
                    'Section_Name' => $sectionName,
                    'Section_Label' => $sectionLabel,
                ];

                $rspMasterDataSection = $this->zohoLib->insertRecord($masterDataSectionFormLinkName, $inputDataSection);
                if (!isset($rspMasterDataSection['result']) || !isset($rspMasterDataSection['result']['pkId'])) {
                    Log::channel('dx')->error('Something error. Can not insert master data section.', [$inputDataSection, $rspMasterDataSection]);
                    throw new \ErrorException('Something error. Can not insert master data section.'. json_encode([$inputDataSection, $rspMasterDataSection]));
                }

                $masterDataSectionZohoId = $rspMasterDataSection['result']['pkId'];
                if ($sectionFields->isNotEmpty()) {
                    foreach ($sectionFields as $field) {
                        $fieldLabelName = $field->label_name;
                        $inputDataField = [
                            'Belong_To_Form' => $masterDataFormZohoId,
                            'Form_Name' => $formDisplayName,
                            'Belong_To_Section' => $masterDataSectionZohoId,
                            'Section_Name' => $sectionName,
                            'Display_Name' => $field->display_name,
                            'Label_Name' => $fieldLabelName,
                            'Comp_Type' => $field->comp_type,
                            'Auto_Fill_Value' => $field->autofillvalue,
                            'Is_Mandatory' => $field->is_mandatory ? "true" : "false",
                            'Decimal_Length' => $field->decimal_length ?? '',
                            'Max_Length' => $field->max_length ?? '',
                        ];

                        $rspMasterDataField = $this->zohoLib->insertRecord($masterDataFieldFormLinkName, $inputDataField);
                        if (!isset($rspMasterDataField['result']) || !isset($rspMasterDataField['result']['pkId'])) {
                            Log::channel('dx')->error('Something error. Can not insert master data field.', [$inputDataField, $rspMasterDataField]);
                            throw new \ErrorException('Something error. Can not insert master data field.'. json_encode([$inputDataField, $rspMasterDataField]));
                        }
                        Log::channel('dx')->info('insert zoho master data field . Form : '. $this->zohoForm->form_link_name . ' Section :' . $sectionLabel . ' Field : ' . $fieldLabelName);
                    }
                }
            }
        }
    }
}
