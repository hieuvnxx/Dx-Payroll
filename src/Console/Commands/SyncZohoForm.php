<?php

namespace Dx\Payroll\Console\Commands;

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
        $arrForm = app(ZohoController::class)->callZoho('forms', [], false);
        if(!empty($arrForm['response']['result'])){
            foreach ($arrForm['response']['result'] as $form) {
                $idForm = ZohoForm::updateOrCreate(
                    ['form_name' => $form['formLinkName']],
                    ['zoho_id' => $form['componentId'],
                        'form_slug' => $form['displayName'],
                        'status' => $form["isVisible"] ? 1 : 0]
                );
                $arrComp = app(ZohoController::class)->getSectionForm('forms/employee/components', 2, false);
                dd(Log::info('ád', $arrComp));
                if (!empty($arrComp['response']['result'])) {
                    foreach ($arrComp['response']['result'] as $data) {
                        if (isset($data['comptype']) && $data['comptype'] == 'AutoNumber') {
                            ZohoFormLabel::updateOrCreate(
                                ['form_name' => $form['displayName'], 'key' => $data['labelname']],
                                ['form_id' => $idForm->id,
                                    'form_slug' => $form['formLinkName'],
                                    'label' => str_replace('_', ' ', $data['displayname']),
                                    'slug' => 'auto_number',]
                            );
                        } else {
                            foreach ($data as $key => $item) {
                                if ($key == 'tabularSections' && !empty($item)) {
                                    foreach ($item as $arr) {
                                        if (!empty($arr)) {
                                            foreach ($arr as $k => $val) {
                                                if ($k !== "sectionId") {
                                                    ZohoFormLabel::updateOrCreate(
                                                        ['form_name' => $form['displayName'], 'key' => $k],
                                                        ['form_id' => $idForm->id,
                                                            'form_slug' => $form['formLinkName'],
                                                            'label' => str_replace('_', ' ', $k),
                                                            'slug' => $k]
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
            }
        }
        echo 'End sync Zoho Form!';
        return 0;
    }
}
