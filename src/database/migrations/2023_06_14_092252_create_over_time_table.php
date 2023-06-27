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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'over_time', function (Blueprint $table) {
            $table->id();
            $table->char('code', 25);
            $table->string('zoho_id', 25)->nullable();
            $table->string('request_id', 25)->nullable();
            $table->string('employee_id', 25)->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('project_task', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('allowance', 255)->nullable();
            $table->date('date', 255)->nullable();
            $table->string('type', 255)->nullable();
            $table->string('from', 255)->nullable();
            $table->string('to', 255)->nullable();
            $table->string('hour', 255)->nullable();
            $table->string('status', 255)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'over_time');
    }
};
