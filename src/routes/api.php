<?php

use Dx\Payroll\Http\Controllers\Api\ZohoForm\UpdateController as ZohoFormUpdateController;
use Dx\Payroll\Http\Controllers\Api\ZohoRecord\InsertController as ZohoRecordInsertController;
use Dx\Payroll\Http\Controllers\Api\ZohoRecord\UpdateController as ZohoRecordUpdateController;
use Dx\Payroll\Http\Controllers\Api\ZohoRecord\DeleteController as ZohoRecordDeleteController;
use Dx\Payroll\Http\Controllers\Api\ZohoRecord\MassInsertController as ZohoRecordMassInsertController;
use Dx\Payroll\Http\Controllers\Api\Payroll\MonthlyWorkingTimeController;
use Dx\Payroll\Http\Controllers\Api\Payroll\OvertimeController;
use Dx\Payroll\Http\Controllers\Api\Payroll\PayslipController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('zoho_form')->group( function() {
        Route::post('update', [ZohoFormUpdateController::class, 'index']);
    });
    Route::prefix('zoho_record')->group( function() {
        Route::post('insert', [ZohoRecordInsertController::class, 'index']);
        Route::post('update', [ZohoRecordUpdateController::class, 'index']);
        Route::post('delete', [ZohoRecordDeleteController::class, 'index']);
        Route::post('mass-insert', [ZohoRecordMassInsertController::class, 'index']);
    });

    Route::prefix('payroll')->group( function () {
        Route::post('monthy_working_time/processByCode', [MonthlyWorkingTimeController::class, 'processByCode']);
        Route::post('monthy_working_time/processAll', [MonthlyWorkingTimeController::class, 'processAll']);

        Route::post('payslip/processByCode', [PayslipController::class, 'processByCode']);
        Route::post('payslip/processAll', [PayslipController::class, 'processAll']);

        Route::post('overtime/updateData', [OvertimeController::class, 'updateData']);
    });
});
