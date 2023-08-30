<?php

namespace Dx\Payroll\Console\Commands;

use Dx\Payroll\Models\ZohoRecordField;
use Dx\Payroll\Models\ZohoSection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dx\Payroll\Models\ZohoForm;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;

class MigrateZohoForm extends Command
{
    protected $signature = 'dxpayroll:migrateZohoForm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to migrate first time database from zoho';

    protected $zohoLib;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info(now()->toDateTimeString() . " Start: dxpayroll:migrateZohoForm");

        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        //get form from zoho with api
        $arrForm = $this->zohoLib->callZoho(zoho_people_fetch_forms_path(), [], false);
        if (!isset($arrForm['response']['result']) || empty($arrForm['response']['result'])) {
            return $this->info('Error get form components!');
        }

        ZohoForm::truncate();
        ZohoSection::truncate();
        ZohoRecordField::truncate();

        DB::beginTransaction();
        try {
            $insertZohoRecordFields = [];
            foreach ($arrForm['response']['result'] as $form) {
                $this->info(now()->toDateTimeString() . " Process Form Name: " . $form['formLinkName']);

                $formCreate = ZohoForm::create([
                    'component_id' => $form['componentId'],
                    'display_name' => $form['displayName'],
                    'form_link_name' => $form['formLinkName'],
                    'is_custom' => $form['iscustom'],
                    'is_visible' => $form['isVisible'],
                    'view_id' => $form['viewDetails']['view_Id'],
                    'view_name' => $form['viewDetails']['view_Name'],
                ]);

                $arrComp = $this->zohoLib->getSectionForm($form['formLinkName'], 2, false);
                if (!isset($arrComp['response']['result']) || empty($arrComp['response']['result'])) {
                    continue;
                }

                foreach ($arrComp['response']['result'] as $data) {
                    if (!empty($data['tabularSections'])) {
                        foreach ($data['tabularSections'] as $sectionData){
                            foreach ($sectionData as $key => $dataSection){
                                if($key != 'sectionId'){
                                    $sectionCreate = ZohoSection::create([
                                        'form_id' => $formCreate->id,
                                        'section_id' => $sectionData['sectionId'],
                                        'section_label' => $key,
                                        'section_name' => ucwords(str_replace('_', ' ', $key)),
                                    ]);

                                    foreach ($dataSection as $sectionField){
                                        $insertZohoRecordFields[] = [
                                            'form_id' => $formCreate->id,
                                            'section_id' => $sectionCreate->id,
                                            'display_name' => $sectionField['displayname'],
                                            'label_name' => $sectionField['labelname'],
                                            'comp_type' => $sectionField['comptype'],
                                            'autofillvalue' => $sectionField['autofillvalue'],
                                            'is_mandatory' => $sectionField['ismandatory'],
                                            'options' => null,
                                            'decimal_length' => $sectionField['decimalLength'] ?? null,
                                            'max_length' => $sectionField['maxLength'] ?? null,
                                        ];
                                    }
                                }
                            }
                        }
                        continue;
                    }

                    $insertZohoRecordFields[] = [
                        'form_id' => $formCreate->id,
                        'section_id' => 0,
                        'display_name' => $data['displayname'],
                        'label_name' => $data['labelname'],
                        'comp_type' => $data['comptype'],
                        'autofillvalue' => $data['autofillvalue'],
                        'is_mandatory' => $data['ismandatory'],
                        'options' => null,
                        'decimal_length' => $data['decimalLength'] ?? null,
                        'max_length' => $data['maxLength'] ?? null,
                    ];
                }
            }

            //chunk to insert database
            $insertZohoRecordFieldsChunk = array_chunk($insertZohoRecordFields, 1000);
            foreach ($insertZohoRecordFieldsChunk as $dataChunk) {
                ZohoRecordField::insert($dataChunk);
            }

            DB::commit();
            return $this->info(now()->toDateTimeString() . " Successfully: dxpayroll:migrateZohoForm");
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error(now()->toDateTimeString() . " Error: dxpayroll:migrateZohoForm ::: Message : " . $e->getMessage(). " ::: Line : " . $e->getLine());
        }
    }
}
