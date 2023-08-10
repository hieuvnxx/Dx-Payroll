<?php

use Dx\Payroll\Http\Controllers\Api\ZohoForm\InsertController as ZohoFormInsertController;
use Dx\Payroll\Http\Controllers\Api\ZohoRecord\InsertController as ZohoRecordInsertController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('zoho_form')->group( function() {
        Route::post('insert', [ZohoFormInsertController::class, 'index']);
    });
    Route::prefix('zoho_record')->group( function() {
        Route::post('insert', [ZohoRecordInsertController::class, 'index']);
    });
});
