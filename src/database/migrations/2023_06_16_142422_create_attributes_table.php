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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'attributes', function (Blueprint $table) {
            $table->id();
            $table->string('form_id', 255);
            $table->string('attributes_name', 255)->nullable();
            $table->string('attributes_label', 255)->nullable();
            $table->string('type', 255)->nullable();
            $table->string('section_id', 255)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'attributes');
    }
};
