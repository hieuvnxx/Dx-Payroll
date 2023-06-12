<?php

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_id', 25)->nullable();
            $table->string('from', 25)->nullable();
            $table->string('to', 25)->nullable();
            $table->string('working_day', 25)->nullable();
            $table->string('haft_working_day', 25)->nullable();
            $table->string('deduction', 25)->nullable();
            $table->string('dependent', 25)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'payroll_settings');
    }
};
