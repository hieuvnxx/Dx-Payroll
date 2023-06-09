<?php

namespace Dx\Payroll\Console\Commands;

use Dx\Payroll\Models\ZohoRecordField;
use Dx\Payroll\Models\ZohoSection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dx\Payroll\Http\Controllers\ZohoController;
use Dx\Payroll\Models\ZohoForm;

class SyncZohoForm extends Command
{
    protected $signature = 'dxpayroll:syncZohoForm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command sync Zoho Form to DB';

    //dependency injecttion
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $zoho = app(ZohoController::class);
        $arrForm = $zoho->callZoho('forms', [], false);
        if (!isset($arrForm['response']['result']) || empty($arrForm['response']['result'])) {
            return $this->info('Nothing to sync!');
        }

        try {
            DB::beginTransaction();
            ZohoForm::query()->delete();
            ZohoSection::query()->delete();
            ZohoRecordField::query()->delete();

            $insertZohoRecordFields = [];
            foreach ($arrForm['response']['result'] as $form) {
                $formCreate = ZohoForm::create([
                    'zoho_id' => $form['componentId'],
                    'form_name' => $form['displayName'],
                    'form_link_name' => $form['formLinkName'],
                    'status' => $form["isVisible"] ? 1 : 0
                ]);
    
                $arrComp = $zoho->getSectionForm('forms/'.$form['formLinkName'].'/components', 2, false);
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
                                            'field_name' => $sectionField['displayname'],
                                            'field_label' => $sectionField['labelname'],
                                            'type' => $sectionField['comptype'],
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
                        'field_name' => $data['displayname'],
                        'field_label' => $data['labelname'],
                        'type' => $data['comptype'],
                    ];
                }
            }

            ZohoRecordField::insert($insertZohoRecordFields);

            DB::commit();
            return $this->info('Successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error('Something went wrong! ' . $e->getMessage());
        }
    }
}
