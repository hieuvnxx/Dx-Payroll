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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'level_settings', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_id', 25)->nullable();
            $table->string('row_id', 25)->nullable();
            $table->string('level', 255)->nullable();
            $table->string('basic_salary', 255)->nullable();
            $table->string('lunch_allowance', 255)->nullable();
            $table->string('travel_allowance', 255)->nullable();
            $table->string('phone_allowance', 255)->nullable();
            $table->string('other_allowance', 255)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'level_settings');
    }
};
