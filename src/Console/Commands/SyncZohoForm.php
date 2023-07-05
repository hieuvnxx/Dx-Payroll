<?php

namespace Dx\Payroll\Console\Commands;

use Dx\Payroll\Models\Attributes;
use Dx\Payroll\Models\Sections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dx\Payroll\Http\Controllers\ZohoController;
use Dx\Payroll\Models\ZohoForm;
use Dx\Payroll\Models\ZohoFormLabel;
use Illuminate\Support\Facades\Log;

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
        if(!empty($arrForm['response']['result'])){
            foreach ($arrForm['response']['result'] as $form) {
                $idForm = ZohoForm::updateOrCreate(
                    [
                        'form_name' => $form['formLinkName']
                    ],[
                        'zoho_id' => $form['componentId'],
                        'form_slug' => $form['displayName'],
                        'status' => $form["isVisible"] ? 1 : 0
                    ]
                );
                $arrComp = $zoho->getSectionForm('forms/'.$form['formLinkName'].'/components', 2, false);
                if (!empty($arrComp['response']['result'])) {
                    foreach ($arrComp['response']['result'] as $data) {
                        if(!isset($data['tabularSections'])){
                            Attributes::updateOrCreate(
                                [
                                    'form_id' => $idForm->id,
                                    'attributes_label' => $data['labelname']
                                ],[
                                    'attributes_name' => $data['displayname'],
                                    'type' => $data['comptype'],
                                    'section_id' => 0,
                                ]
                            );
                        }else{
                            foreach ($data['tabularSections'] as $sectionData){
                                foreach ($sectionData as $key => $dataSection){
                                    if($key != 'sectionId'){
                                        $sectionID = Sections::updateOrCreate(
                                            [
                                                'form_id' => $idForm->id,
                                                'sections_label' => $key,
                                            ],[
                                                'sections_name' => ucwords(str_replace('_', ' ', $key)),
                                                'sections_id' => $sectionData['sectionId'],
                                            ]
                                        );
                                        foreach ($dataSection as $sectionAttribute){
                                            Attributes::updateOrCreate(
                                                [
                                                    'form_id' => $idForm->id,
                                                    'attributes_label' => $sectionAttribute['labelname'],
                                                    'section_id' => $sectionID->id
                                                ],[
                                                    'attributes_name' => $sectionAttribute['displayname'],
                                                    'type' => $sectionAttribute['comptype'],
                                                ]
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        echo 'End sync Zoho Form!';
        return 0;
    }
}
