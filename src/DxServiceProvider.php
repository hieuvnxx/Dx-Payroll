<?php

namespace Dx\Payroll;

use Dx\Payroll\Exceptions\DxHandler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DxServiceProvider extends ServiceProvider
{
    public const DX_PAYROLL_NAMESPACE = 'Dx\Payroll';
    public const DX_PREFIX_TABLE = 'dx_';
    public const DX_SALARY_FORM_LINK_NAME = [
        'monthly_working_time' => [
            'label' => 'monthly_working_time',
            'name'  => 'Monthly working time/Bảng công',
        ],

        'payslip'   => [
            'label' => 'payslip1',
            'name'  => 'Payslip/ Bảng lương',
        ],

        'overtime_request' => [
            'label' => 'ot_request',
            'name'  => 'OT Request/Yêu cầu làm ngoài giờ',
        ],

        'constant_configuration' => [
            'label' => 'setting',
            'name'  => 'Constant configuration/Cấu hình hằng số',
        ],

        'form_master_data' => [
            'label' => 'form_master_data',
            'name'  => 'Constant configuration/Cấu hình hằng số',
        ],

        'factor_master_data' => [
            'label' => 'factor_master_data',
            'name'  => 'Salary factor/Nhân tố lương',
        ],

        'formula' => [
            'label' => 'fomular',
            'name'  => 'Formula Source/Kho công thức',
        ]
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadCommands();

        $this->app->singleton(ExceptionHandler::class,DxHandler::class);
        $this->app->bind(\Dx\Payroll\Repositories\ZohoFormInterface::class,\Dx\Payroll\Repositories\Eloquent\ZohoFormRepository::class);
        $this->app->bind(\Dx\Payroll\Repositories\ZohoRecordInterface::class,\Dx\Payroll\Repositories\Eloquent\ZohoRecordRepository::class);
        $this->app->bind(\Dx\Payroll\Repositories\ZohoSectionInterface::class,\Dx\Payroll\Repositories\Eloquent\ZohoSectionRepository::class);

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        Route::prefix('api/dx_payroll/v1')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        });

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->mergeConfigFrom(__DIR__.'/config/logging.php','logging.channels');
    }

    public function loadCommands()
    {
        $arrayCommands = [];
        $commandDir = glob(__DIR__ . '\Console\Commands\*');
        foreach ($commandDir as $commandFile) {
            $arrayCommands[] = DxServiceProvider::DX_PAYROLL_NAMESPACE . '\Console\Commands\\'  .pathinfo($commandFile, PATHINFO_FILENAME);
        }
        $this->commands($arrayCommands);
    }
}
