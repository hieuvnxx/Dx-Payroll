<?php

namespace Dx\Payroll\Jobs\Payroll;

use Dx\Payroll\Http\Controllers\Api\Payroll\MonthlyWorkingTimeController;
use Dx\Payroll\Integrations\ZohoPeopleIntegration;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;

class PushMonthyWorkingTimePerEmployeeToZoho implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    private $employee;
    private $month;

    private $dxPayrollConfig;
    private $zohoLib;
    private $zohoForm;
    private $zohoRecord;
    private $zohoRecordValue;

    /**
     * Create a new job instance.
     * the dataJob sample :
     * [
     *      'employee' => [...]
     *      'month' => '05-2023'
     * ]
     * @return void
     */
    public function __construct($dataJob)
    {
        $this->employee = $dataJob['employee'];
        $this->month = $dataJob['month'];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ZohoFormInterface $zohoForm, ZohoRecordInterface $zohoRecord, ZohoRecordValueInterface $zohoRecordValue)
    {
        $this->zohoLib = ZohoPeopleIntegration::getInstance();

        $this->zohoForm = $zohoForm;
        $this->zohoRecord = $zohoRecord;
        $this->zohoRecordValue = $zohoRecordValue;
        $this->dxPayrollConfig = config('dx_payroll');
        $monthlyWorkingTimeApiController = app(MonthlyWorkingTimeController::class);

        $monthlyPayrollModuleConfigDynamic = $this->dxPayrollConfig['monthly_working_time'];
        $employeeIdNumberFieldLabel = Env::get('EMPLOYEE_FORM_ID_NUMBER_FIELD_LABEL');
        $constantConfigFormLinkName = Env::get('PAYROLL_CONSTANT_CONFIGURATION_FORM_LINK_NAME');
        $monthlyWorkingTimeFormLinkName = Env::get('PAYROLL_MONTHY_WORKING_TIME_FORM_LINK_NAME');

        $employee = $this->employee;
        $month    = $this->month;
        $empCode = $employee[$employeeIdNumberFieldLabel];
        $monthly = str_replace('-', '/', $month);
        $code = $empCode . "-" . $monthly;

        $formEav = $this->zohoForm->has('attributes', 'sections', 'sections.attributes')->with('attributes', 'sections', 'sections.attributes')->where('form_link_name', $monthlyWorkingTimeFormLinkName)->first();
        if (is_null($formEav)) {
            Log::channel('dx')->info('formEav is null ::: empCode :::' . $empCode);
            throw new \ErrorException('formEav is null');
        }

        $constantConfigs = $this->zohoRecord->getRecords($constantConfigFormLinkName);
        if (empty($constantConfigs)) {
            Log::channel('dx')->info('Constant configuration empty in database. Please re-generate constant configuration.' . $empCode);
            throw new \ErrorException('Constant configuration empty in database. Please re-generate constant configuration.');
        }

        $constantConfig = $constantConfigs[0];

        list($fromSalary, $toSalary) = payroll_range_date($month, $constantConfig['from_date'], $constantConfig['to_date']);

        // fetch all punch in - out
        /*
        * pending get data from database
        */
        $employeeDataPunch = $this->zohoLib->getAttendanceByEmployee($empCode, $fromSalary, $toSalary);

        // get punch shift by employee data punch range payslip
        /*
        * pending get data from database
        */
        $dataShiftConfig = $this->zohoLib->getShiftConfigurationByEmployee($empCode, $fromSalary, $toSalary, $employeeDataPunch);

        // get all leave
        /*
        * pending get data from database
        */
        $leaves = $monthlyWorkingTimeApiController->getLeaveWorking($empCode, $fromSalary, $toSalary);

        // fetch all overtime request range payslip
        /*
        * pending get data from database
        */
        $overtimes = $monthlyWorkingTimeApiController->getOverTime($empCode, $fromSalary, $toSalary);

        // Kiểm tra xem đã tồn tại monthly working report chưa
        $existMonthlyIds = [];
        $monthlyWorkingTimeExistZoho = $this->zohoRecord->getRecords($monthlyWorkingTimeFormLinkName, 0, 200, [
            'employee' => [
                'searchText' => $empCode,
                'searchOperator' => 'Contains'
            ],
            'salary_period' => 
            [
                'searchText' => $monthly,
                'searchOperator' => 'Is',
            ]
        ]);

        $monthlyDataCollect = $monthlyWorkingTimeApiController->processUpdateData($dataShiftConfig, $constantConfig, $employee, $leaves, $overtimes, $formEav);

        $paidLeave = $monthlyDataCollect['paid_leave'];
        $holidayCount = $monthlyDataCollect['holiday_count'];
        $standardWorkingDay = $monthlyDataCollect['standard_working_day'];
        $standardWorkingDayProbation = $monthlyDataCollect['standard_working_day_probation'];
        $totalWorkingDays = sum_number([$standardWorkingDay, $standardWorkingDayProbation]);
        $totalSalaryWorkingDay = sum_number([$totalWorkingDays, $holidayCount, $paidLeave]);

        $inputData = [
            $monthlyPayrollModuleConfigDynamic['code']                                  => $code,
            $monthlyPayrollModuleConfigDynamic['employee']                              => $employee['Zoho_ID'],
            $monthlyPayrollModuleConfigDynamic['salary_period']                         => $monthly,
            $monthlyPayrollModuleConfigDynamic['standard_working_time']                 => $monthlyDataCollect['standard_working_time'],
            $monthlyPayrollModuleConfigDynamic['overtime_meal_allowance']               => $monthlyDataCollect['meal_allowance'],
            $monthlyPayrollModuleConfigDynamic['total_working_days']                    => $totalWorkingDays,
            $monthlyPayrollModuleConfigDynamic['standard_working_day_probation']        => $standardWorkingDayProbation,
            $monthlyPayrollModuleConfigDynamic['standard_working_day']                  => $standardWorkingDay,
            $monthlyPayrollModuleConfigDynamic['holidays']                              => $holidayCount,
            $monthlyPayrollModuleConfigDynamic['paid_leave']                            => $paidLeave,
            $monthlyPayrollModuleConfigDynamic['total_salary_working_day']              => $totalSalaryWorkingDay,
            $monthlyPayrollModuleConfigDynamic['weekday_hour']                          => $monthlyDataCollect['week_day_hour'],
            $monthlyPayrollModuleConfigDynamic['weekend_hour']                          => $monthlyDataCollect['weekend_day_hour'],
            $monthlyPayrollModuleConfigDynamic['holiday_hour']                          => $monthlyDataCollect['holiday_day_hour'],
            $monthlyPayrollModuleConfigDynamic['weekday_night_hour']                    => $monthlyDataCollect['week_night_hour'],
            $monthlyPayrollModuleConfigDynamic['weekend_night_hour']                    => $monthlyDataCollect['weekend_night_hour'],
            $monthlyPayrollModuleConfigDynamic['holiday_night_hour']                    => $monthlyDataCollect['holiday_night_hour'],
        ];

        $tabularData = $monthlyDataCollect['tabular_data'];

        /* remove exist record in zoho if exist */
        if (!empty($monthlyWorkingTimeExistZoho[0])) {
            $existMonthlyIds = collect($monthlyWorkingTimeExistZoho)->map(function ($data) {
                return $data['Zoho_ID'];
            })->toArray();

            $existToUpdateZohoId = array_shift($existMonthlyIds);
            if (!empty($existMonthlyIds)) {
                $existMonthlyStringIds = implode(',', $existMonthlyIds);
                $rspDeleteMonthlyWorkingTimeExistZoho = $this->zohoLib->deleteRecords($monthlyWorkingTimeFormLinkName, $existMonthlyStringIds);
            }

            foreach ($monthlyWorkingTimeExistZoho as $monthlyWorkingTime) {
                if ($monthlyWorkingTime['Zoho_ID'] == $existToUpdateZohoId) {
                    $monthlyWorkingTimeApiController->removeExistTabularZoho($tabularData, $monthlyWorkingTime, $formEav);
                }
            }

            if (empty($tabularData)) {
                Log::channel('dx')->info('Something error. Can not generate attendance detail exist. empCode:' . $empCode . ' . Zoho_ID:' . $existToUpdateZohoId);
                throw new \ErrorException('Something error. Can not generate attendance detail exist. empCode: ' . $empCode . ' . Zoho_ID:' . $existToUpdateZohoId);
            }

            $rspUpdate = $this->zohoLib->updateRecord($monthlyWorkingTimeFormLinkName, $inputData, $tabularData, $existToUpdateZohoId, 'yyyy-MM-dd');
            if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
                Log::channel('dx')->info('Something error. Can not update attendance detail to record monthy working time with exist Zoho_ID:' . $existToUpdateZohoId);
                throw new \ErrorException('Something error. Can not update attendance detail to record monthy working time with exist Zoho_ID: ' . $existToUpdateZohoId);
            }

            return;
        }

        if (empty($tabularData)) {
            Log::channel('dx')->info('Something error. Can not generate attendance detail. empCode:' . $empCode);
            throw new \ErrorException('Something error. Can not generate attendance detail. empCode: ' . $empCode);
        }
        
        $rspInsert = $this->zohoLib->insertRecord($monthlyWorkingTimeFormLinkName, $inputData, 'yyyy-MM-dd');
        if (!isset($rspInsert['result']) || !isset($rspInsert['result']['pkId'])) {
            Log::channel('dx')->info('Something error. Can not insert new record monthy working time in to zoho. empCode:' . $empCode);
            throw new \ErrorException('Something error. Can not insert new record monthy working time in to zoho.');
        }

        $zohoId = $rspInsert['result']['pkId'];

        $rspUpdate = $this->zohoLib->updateRecord($monthlyWorkingTimeFormLinkName, $inputData, $tabularData, $zohoId, 'yyyy-MM-dd');
        if (!isset($rspUpdate['result']) || !isset($rspUpdate['result']['pkId'])) {
            Log::channel('dx')->info('Something error. Can not update attendance detail to record monthy working time with id :' . $zohoId);
            throw new \ErrorException('Something error. Can not update attendance detail to record monthy working time with id : '. $zohoId);
        }
    }
}
