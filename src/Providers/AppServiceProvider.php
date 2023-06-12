<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // register repository
        /*
         * Example :
         * $this->app->bind(TimesheetRepository::class, TimesheetRepositoryEloquent::class);
         *
         */
   }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
	    Schema::defaultStringLength(191);

//        $this->app->bind(\Dx\Payroll\Repositories\EmployeeInterface::class,\Dx\Payroll\Repositories\Eloquent\EmployeeRepository::class);
    }
}
