<?php

namespace Dx\Payroll\Console\Commands;

use Illuminate\Console\Command;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Illuminate\Support\Env;

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

    public function __construct(ZohoFormInterface $zohoForm)
    {
        parent::__construct();
        
        $this->zohoLib = ZohoPeopleIntegration::getInstance();
        $this->zohoForm = $zohoForm;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info(now()->toDateTimeString() . " Start: dxpayroll:migrateDataPayrollModuleFromZoho");

        return $this->info(now()->toDateTimeString() . " Successfully: dxpayroll:migrateDataPayrollModuleFromZoho");
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

        $departmentForm = $this->zohoForm->with(['sections', 'attributes', 'sections.attributes'])->where('form_link_name', $departmentFormLinkName)->get();
        if ($departmentForm->isEmpty()) {
            return $this->info(now()->toDateTimeString() . " Error: dxpayroll:migrateDataOrganizationFromZoho ::: $departmentFormLinkName env not generate in database");
        }

        $departmentForm = $departmentForm[0];

        $index = 1;
        $offset = 200;
        while (true) {
            $departmentRecords         = $this->zohoLib->getRecords($departmentFormLinkName, $index, $offset);

            if (empty($departmentRecords) || isset($departmentRecords['errors']) || (isset($departmentRecords['status']) && $departmentRecords['status'] != 0)) break;

            foreach ($departmentRecords as $employee) {
                $zohoRecord = ZohoRecord::updateOrCreate(['form_id' => $departmentForm->id, 'zoho_id' => (string)$employee['Zoho_ID']]);

                $zohoFormSections = $departmentForm->sections->keyBy('section_name');
                if (!empty($employee['tabularSections'])) {
                    foreach ($employee['tabularSections'] as $tabularName => $values) {
                        if (!isset($zohoFormSections[$tabularName]) || empty($values[0])) continue;

                        foreach ($values as $value) {
                            ZohoRecordValue::createOrUpdateZohoRecordValue($zohoFormSections[$tabularName]->attributes->keyBy('field_label'), $zohoRecord, $value, $value['tabular.ROWID']);
                        }
                    }
                    unset($employee['tabularSections']);
                }

                ZohoRecordValue::createOrUpdateZohoRecordValue($departmentForm->attributes->keyBy('field_label'), $zohoRecord, $employee);
            }

            $index += $offset;
        }
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

        $employeeForm = $this->zohoForm->with(['sections', 'attributes', 'sections.attributes'])->where('form_link_name', $employeeFormLinkName)->get();
        if ($employeeForm->isEmpty()) {
            return $this->info(now()->toDateTimeString() . " Error: dxpayroll:migrateDataOrganizationFromZoho ::: $employeeFormLinkName env not generate in database");
        }

        $employeeForm = $employeeForm[0];

        $index = 1;
        $offset = 200;
        while (true) {
            $employeeRecords         = $this->zohoLib->getRecords($employeeFormLinkName, $index, $offset);

            if (empty($employeeRecords) || isset($employeeRecords['errors']) || (isset($employeeRecords['status']) && $employeeRecords['status'] != 0)) break;

            foreach ($employeeRecords as $employee) {
                $zohoRecord = ZohoRecord::updateOrCreate(['form_id' => $employeeForm->id, 'zoho_id' => (string)$employee['Zoho_ID']]);

                $zohoFormSections = $employeeForm->sections->keyBy('section_name');
                if (!empty($employee['tabularSections'])) {
                    foreach ($employee['tabularSections'] as $tabularName => $values) {
                        if (!isset($zohoFormSections[$tabularName]) || empty($values[0])) continue;

                        foreach ($values as $value) {
                            ZohoRecordValue::createOrUpdateZohoRecordValue($zohoFormSections[$tabularName]->attributes->keyBy('field_label'), $zohoRecord, $value, $value['tabular.ROWID']);
                        }
                    }
                    unset($employee['tabularSections']);
                }

                ZohoRecordValue::createOrUpdateZohoRecordValue($employeeForm->attributes->keyBy('field_label'), $zohoRecord, $employee);
            }

            $index += $offset;
        }
    }
}
