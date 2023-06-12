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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'zoho_form_label', function (Blueprint $table) {
            $table->id();
            $table->string('form_id', 25)->nullable();
            $table->string('slug', 255)->nullable();
            $table->string('form_name', 255)->nullable();
            $table->string('label', 255)->nullable();
            $table->string('form_slug', 255)->nullable();
            $table->string('key', 255)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'zoho_form_label');
    }
};
