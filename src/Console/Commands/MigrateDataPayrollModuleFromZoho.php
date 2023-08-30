<?php

namespace Dx\Payroll\Console\Commands;

use Illuminate\Support\Env;
use Illuminate\Console\Command;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Jobs\ProcessSyncDataFormLinkName;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;

class MigrateDataPayrollModuleFromZoho extends Command
{
    protected $signature = 'dxpayroll:migrateDataPayrollModuleFromZoho';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generate payroll module zoho form';

    protected $zohoLib;
    protected $zohoForm;
    protected $zohoRecord;

    public function __construct(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord)
    {
        parent::__construct();
        
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
        $this->info(now()->toDateTimeString() . " Start: dxpayroll:migrateDataPayrollModuleFromZoho");

        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $modulePayrollFormLinkNames = [
            'PAYROLL_PAYSLIP_FORM_LINK_NAME' => Env::get('PAYROLL_PAYSLIP_FORM_LINK_NAME', null),
            'PAYROLL_MONTHY_WORKING_TIME_FORM_LINK_NAME' => Env::get('PAYROLL_MONTHY_WORKING_TIME_FORM_LINK_NAME', null),
            'PAYROLL_OT_REQUEST_FORM_LINK_NAME' => Env::get('PAYROLL_OT_REQUEST_FORM_LINK_NAME', null),
            'PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME' => Env::get('PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME', null),
            'PAYROLL_FORM_MASTER_DATA_FORM_LINK_NAME' => Env::get('PAYROLL_FORM_MASTER_DATA_FORM_LINK_NAME', null),
            'PAYROLL_SALARY_FACTOR_FORM_LINK_NAME' => Env::get('PAYROLL_SALARY_FACTOR_FORM_LINK_NAME', null),
            'PAYROLL_FORMULA_SOURCE_FORM_LINK_NAME' => Env::get('PAYROLL_FORMULA_SOURCE_FORM_LINK_NAME', null),
            'MASTER_DATA_SHIFT_FORM_LINK_NAME' => Env::get('MASTER_DATA_SHIFT_FORM_LINK_NAME', null),
        ];

        foreach ($modulePayrollFormLinkNames as $envKey => $formLinkName) {
            $this->migrateFormData($envKey, $formLinkName);
        }

        return $this->info(now()->toDateTimeString() . " Successfully: dxpayroll:migrateDataPayrollModuleFromZoho");
    }

   /**
    * payroll module
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
