<?php

namespace Dx\Payroll\Console\Commands;

use Illuminate\Support\Env;
use Dx\Payroll\Jobs\ProcessSyncDataFormLinkName;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateDataOrganizationFromZoho extends Command
{
    protected $signature = 'dxpayroll:migrateDataOrganizationFromZoho';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Contract Types Employee';

    protected $zohoLib;
    protected $zohoForm;
    protected $zohoRecord;

    public function __construct(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord)
    {
        parent::__construct();
        
        $this->zohoLib = ZohoPeopleIntegration::getInstance();
        $this->zohoForm = $zohoForm;
        $this->zohoRecord = $zohoRecord;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info(now()->toDateTimeString() . " Start: dxpayroll:migrateDataOrganizationFromZoho");

        $modulePayrollFormLinkNames = [
            'DEPARTMENT_FORM_LINK_NAME' => Env::get('DEPARTMENT_FORM_LINK_NAME', null),
            'EMPLOYEE_FORM_LINK_NAME' => Env::get('EMPLOYEE_FORM_LINK_NAME', null),
        ];

        foreach ($modulePayrollFormLinkNames as $envKey => $formLinkName) {
            $this->migrateFormData($envKey, $formLinkName);
        }

        return $this->info(now()->toDateTimeString() . " Successfully: dxpayroll:migrateDataOrganizationFromZoho");
    }

    /**
    * organization module
    */
    private function migrateFormData($envKey, $formLinkName)
    {
        if (is_null($formLinkName)) {
            throw new \ErrorException($envKey . ' env not config');
        }

        $cloneZohoFormInterface = clone $this->zohoForm;
        $zohoForm = $cloneZohoFormInterface->with(['sections', 'attributes', 'sections.attributes'])->where('form_link_name', $formLinkName)->first();
        if (is_null($zohoForm)) {
            throw new \ErrorException('Not found '.$formLinkName.' in database');
        }

        ProcessSyncDataFormLinkName::dispatch($formLinkName, $zohoForm);
    }
}
