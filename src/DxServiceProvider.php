<?php

namespace Dx\Payroll;

use Illuminate\Support\ServiceProvider;

class DxServiceProvider extends ServiceProvider
{

    public const DX_PREFIX_TABLE = 'dx_';

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
