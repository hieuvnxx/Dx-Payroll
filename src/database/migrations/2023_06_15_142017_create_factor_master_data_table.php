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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'factor_master_data', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_id', 25)->nullable();
            $table->string('factor', 255)->nullable();
            $table->string('abbreviation', 255)->nullable();
            $table->string('type', 255)->nullable();
            $table->string('form_name', 255)->nullable();
            $table->string('field_name', 255)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'factor_master_data');
    }
};
