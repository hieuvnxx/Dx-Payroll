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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'fomular', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_id', 25)->nullable();
            $table->string('factor_1', 255)->nullable();
            $table->string('factor_2', 255)->nullable();
            $table->string('factor_3', 255)->nullable();
            $table->string('factor_4', 255)->nullable();
            $table->string('factor_5', 255)->nullable();
            $table->string('factor_6', 255)->nullable();
            $table->string('factor_7', 255)->nullable();
            $table->string('factor_8', 255)->nullable();
            $table->string('factor_9', 255)->nullable();
            $table->string('factor_10', 255)->nullable();
            $table->string('factor_11', 255)->nullable();
            $table->string('factor_12', 255)->nullable();
            $table->string('factor_13', 255)->nullable();
            $table->string('factor_14', 255)->nullable();
            $table->string('field', 255)->nullable();
            $table->string('fomular', 255)->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('contract_type', 255)->nullable();
            $table->string('type', 255)->nullable();
            $table->string('department', 255)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'fomular');
    }
};
