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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'employees', function (Blueprint $table) {
            $table->id();
            $table->char('code', 25)->unique();
            $table->string('zoho_id', 25)->nullable();
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('work_phone', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('level', 255)->nullable();
            $table->string('department', 255)->nullable();
            $table->string('contract_type', 255)->nullable();
            $table->string('probation_rate', 255)->nullable();
            $table->dateTime('do_birth')->nullable();
            $table->dateTime('do_join')->nullable();
            $table->dateTime('do_sign')->nullable();
            $table->string('self_deduction', 255)->nullable();
            $table->string('join_social_insurance', 255)->nullable();
            $table->string('join_trade_union', 255)->nullable();
            $table->string('salary_allowances', 255)->nullable();
            $table->string('insurance_month', 255)->nullable();
            $table->string('union_month', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('status_payroll', 255)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'employees');
    }
};
