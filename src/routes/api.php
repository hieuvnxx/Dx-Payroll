<?php

use Dx\Payroll\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;
use Dx\Payroll\Http\Controllers\PayrollController;
use Dx\Payroll\Http\Controllers\SyncDataController;

Route::post('payroll', [PayrollController::class, 'payrollProcess']);
Route::post('sync-data', [SyncDataController::class, 'processSyncData']);
