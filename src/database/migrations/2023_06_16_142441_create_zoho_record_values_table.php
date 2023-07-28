<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Dx\Payroll\DxServiceProvider;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('record_id'); //$table->foreignId('form_id')->constrained(DxServiceProvider::DX_PREFIX_TABLE.'zoho_forms');
            $table->unsignedBigInteger('field_id'); //$table->foreignId('field_id')->constrained(DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_fields');
            $table->unsignedBigInteger('section_id')->nullable()->default(0); //$table->foreignId('field_id')->constrained(DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_fields');
            $table->unsignedBigInteger('row_id')->nullable()->default(0);
            $table->text('value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_values');
    }
};
