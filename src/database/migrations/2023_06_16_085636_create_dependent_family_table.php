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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'dependent_family', function (Blueprint $table) {
            $table->id();
            $table->char('code', 25);
            $table->string('zoho_id', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('relation', 255)->nullable();
            $table->string('is_dependent', 255)->nullable();
            $table->dateTime('do_birth')->nullable();
            $table->dateTime('from_date')->nullable();
            $table->dateTime('to_date')->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'dependent_family');
    }
};
