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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'_employees', function (Blueprint $table) {
            $table->id();
            $table->char('code', 25)->unique();
            $table->string('first_name', 55)->nullable();
            $table->string('last_name', 55)->nullable();
            $table->string('email', 75)->nullable();
            $table->string('phone', 75)->nullable();
            $table->string('work_phone', 75)->nullable();
            $table->dateTime('dob')->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'_employees');
    }
};
