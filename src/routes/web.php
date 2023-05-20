<?php

use Dx\Payroll\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/test-route-dxsmartosc-package-payroll', function () {
    return "test-route-dxsmartosc-package-payroll";
});

Route::get('/test-route-dxsmartosc-package-payroll-employeee-controller-index', [EmployeeController::class, 'index']);