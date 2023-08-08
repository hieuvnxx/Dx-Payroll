<?php

namespace Dx\Payroll\Console\Commands;

use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Models\Payroll\ContractType;
use Dx\Payroll\Models\ZohoRecord;
use Dx\Payroll\Models\ZohoRecordValue;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Env;

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
        /*
        * Contract Type
        */
        ContractType::truncate();

        $contractTypes = [
            ['name' => 'Thử việc và chính thức'],
            ['name' => 'Thử việc'],
            ['name' => 'Chính thức']
        ];

        ContractType::insert($contractTypes);

        /*
        * Department
        */
        $this->migrateDepartmentData();

        /*
        * Employee
        */
        $this->migrateEmployeeData();

        return $this->info(now()->toDateTimeString() . " Successfully: dxpayroll:migrateDataOrganizationFromZoho");
    }

    /**
    * Department
    */
    private function migrateDepartmentData()
    {
        $departmentFormLinkName = Env::get('DEPARTMENT_FORM_LINK_NAME', null);
        if (is_null($departmentFormLinkName)) {
            return $this->info(now()->toDateTimeString() . " Error: dxpayroll:migrateDataOrganizationFromZoho ::: DEPARTMENT_FORM_LINK_NAME env not config");
        }

        $this->migrateDataFormLinkName($departmentFormLinkName);
    }

    /**
    * Employee
    */
    private function migrateEmployeeData()
    {
        $employeeFormLinkName = Env::get('EMPLOYEE_FORM_LINK_NAME', null);
        if (is_null($employeeFormLinkName)) {
            return $this->info(now()->toDateTimeString() . " Error: dxpayroll:migrateDataOrganizationFromZoho ::: EMPLOYEE_FORM_LINK_NAME env not config");
        }

        $this->migrateDataFormLinkName($employeeFormLinkName);
    }

    private function migrateDataFormLinkName($formLinkName)
    {
        $zohoForm = $this->zohoForm->with(['sections', 'attributes', 'sections.attributes'])->where('form_link_name', $formLinkName)->get();
        if ($zohoForm->isEmpty()) {
            return $this->info(now()->toDateTimeString() . " Error: dxpayroll:migrateDataOrganizationFromZoho ::: $formLinkName env not generate in database");
        }

        $zohoForm = $zohoForm[0];

        $index = 1;
        $offset = 200;

        while (true) {
            $responseZohoRecords         = $this->zohoLib->getRecords($formLinkName, $index, $offset);

            if (empty($responseZohoRecords) || isset($responseZohoRecords['errors']) || (isset($responseZohoRecords['status']) && $responseZohoRecords['status'] != 0)) break;

            foreach ($responseZohoRecords as $responseRecordData) {
                $zohoRecord = ZohoRecord::updateOrCreate(['form_id' => $zohoForm->id, 'zoho_id' => (string)$responseRecordData['Zoho_ID']]);

                $zohoFormSections = $zohoForm->sections->keyBy('section_name');
                if (!empty($responseRecordData['tabularSections'])) {
                    foreach ($responseRecordData['tabularSections'] as $tabularName => $values) {
                        if (!isset($zohoFormSections[$tabularName]) || empty($values[0])) continue;

                        foreach ($values as $value) {
                            ZohoRecordValue::createOrUpdateZohoRecordValue($zohoFormSections[$tabularName]->attributes->keyBy('field_label'), $zohoRecord, $value, $value['tabular.ROWID']);
                        }
                    }
                    unset($responseRecordData['tabularSections']);
                }

                ZohoRecordValue::createOrUpdateZohoRecordValue($zohoForm->attributes->keyBy('field_label'), $zohoRecord, $responseRecordData);
            }

            $index += $offset;
        }
    }
}
