<?php

namespace Dx\Payroll\Jobs;

use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Models\ZohoForm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSyncDataFormLinkName implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected $zohoLib;

    protected $zohoForm;

    protected $zohoRecord;

    protected $zohoRecordValue;
    
    protected $formLinkName;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($formLinkName, ZohoForm $zohoForm)
    {
        $this->formLinkName = $formLinkName;
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

        $index = 1;
        $offset = 200;

        while (true) {
            $responseZohoRecords         = $this->zohoLib->getRecords($this->formLinkName, $index, $offset);

            if (empty($responseZohoRecords) || isset($responseZohoRecords['errors']) || (isset($responseZohoRecords['status']) && $responseZohoRecords['status'] != 0)) break;

            ProcessSyncBatchDataFormLinkName::dispatch($this->zohoForm, $responseZohoRecords);

            $index += $offset;
        }
    }
}
