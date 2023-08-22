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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'zoho_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zoho_id');
            $table->unsignedBigInteger('form_id');
            // $table->foreignId('form_id')->constrained(DxServiceProvider::DX_PREFIX_TABLE.'zoho_forms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'zoho_records');
    }
};
