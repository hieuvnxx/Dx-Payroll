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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'zoho_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('component_id');
            $table->string('display_name', 255);
            $table->string('form_link_name', 255);
            $table->tinyInteger('is_custom');
            $table->tinyInteger('is_visible');
            $table->unsignedBigInteger('view_id');
            $table->string('view_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'zoho_forms');
    }
};
