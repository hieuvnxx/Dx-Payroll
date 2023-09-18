<?php

namespace Dx\Payroll\Jobs;

use Dx\Payroll\Models\ZohoForm;
use Dx\Payroll\Models\ZohoRecord;
use Dx\Payroll\Models\ZohoRecordValue;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSyncBatchDataFormLinkName implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected $zohoForm;

    protected $responseZohoRecords;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ZohoForm $zohoForm, $responseZohoRecords)
    {
        $this->zohoForm = $zohoForm;
        $this->responseZohoRecords = $responseZohoRecords;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $responseZohoRecords  =  $this->responseZohoRecords;

        DB::beginTransaction();
        try {
            foreach ($responseZohoRecords as $responseRecordData) {
                Log::channel('dx')->info('ProcessSyncBatchDataFormLinkName ::: formLinkName ::: [' . $this->zohoForm->form_link_name .'] recordId ::: [' . $responseRecordData['Zoho_ID'] .']');

                $zohoRecord = ZohoRecord::updateOrCreate(['form_id' => $this->zohoForm->id, 'zoho_id' => (string)$responseRecordData['Zoho_ID']]);
                
                $zohoFormSections = $this->zohoForm->sections->keyBy('section_name');
                if (!empty($responseRecordData['tabularSections'])) {
                    foreach ($responseRecordData['tabularSections'] as $tabularName => $values) {
                        if (!isset($zohoFormSections[$tabularName]) || empty($values[0])) continue;

                        foreach ($values as $value) {
                            ZohoRecordValue::createOrUpdateZohoRecordValue($zohoFormSections[$tabularName]->attributes->keyBy('label_name'), $zohoRecord, $value, $value['tabular.ROWID']);
                        }
                    }
                    unset($responseRecordData['tabularSections']);
                }
                
                ZohoRecordValue::createOrUpdateZohoRecordValue($this->zohoForm->attributes->keyBy('label_name'), $zohoRecord, $responseRecordData);
            }

            DB::commit();
        } catch (Exception $e) {
            throw new \ErrorException('Something error. Can not sync batch data form link name.'. $e->getMessage());
            DB::rollback();
        }
    }
}
