<?php

namespace Dx\Payroll;

use Dx\Payroll\Exceptions\DxHandler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DxServiceProvider extends ServiceProvider
{
    public const DX_PAYROLL_NAMESPACE = 'Dx\Payroll';
    public const DX_PREFIX_TABLE = 'dx_';

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

        Route::prefix(Env::get("PAYROLL_API_PREFIX_VERSION", "api/dx_payroll/v1"))->group(function () {
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
