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
        $this->app->bind(\Dx\Payroll\Repositories\RefreshTokenInterface::class,\Dx\Payroll\Repositories\Eloquent\RefreshTokenRepository::class);
        $this->app->bind(\Dx\Payroll\Repositories\ZohoFormInterface::class,\Dx\Payroll\Repositories\Eloquent\ZohoFormRepository::class);
        $this->app->bind(\Dx\Payroll\Repositories\RedisConfigFormInterface::class,\Dx\Payroll\Repositories\Eloquent\RedisConfigFormRepository::class);
        $this->app->bind(\Dx\Payroll\Repositories\RecordsInterface::class,\Dx\Payroll\Repositories\Eloquent\RecordsRepository::class);
        $this->app->bind(\Dx\Payroll\Repositories\SectionsInterface::class,\Dx\Payroll\Repositories\Eloquent\SectionsRepository::class);
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        Route::prefix('api/dx_payroll/v1')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        });
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->mergeConfigFrom(__DIR__.'/config/logging.php','logging.channels');
    }

    public function loadCommands()
    {
        if ($this->app->runningInConsole()) {
            $arrayCommands = [];
            $commandDir = glob(__DIR__ . '\Console\Commands\*');
            foreach ($commandDir as $commandFile) {
                $arrayCommands[] = DxServiceProvider::DX_PAYROLL_NAMESPACE . '\Console\Commands\\'  .pathinfo($commandFile, PATHINFO_FILENAME);
            }
            $this->commands($arrayCommands);
        }
    }
}
