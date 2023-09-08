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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_fields', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('form_id')->constrained(DxServiceProvider::DX_PREFIX_TABLE.'zoho_forms');
            $table->unsignedBigInteger('section_id')->nullable()->default(0); 
            $table->unsignedBigInteger('form_id'); 
            // $table->foreignId('section_id')->constrained(DxServiceProvider::DX_PREFIX_TABLE.'zoho_sections');
            $table->string('display_name', 255)->nullable();
            $table->string('label_name', 255)->nullable();
            $table->char('comp_type', 75)->nullable();
            $table->text('autofillvalue')->comment('require input');
            $table->boolean('is_mandatory')->comment('require input');
            $table->longText('options')->nullable()->comment('additional for field type picklist, lookup');
            $table->tinyInteger('decimal_length')->nullable()->comment('additional for field type decimal');
            $table->integer('max_length')->nullable()->comment('additional for field type picklist, lookup');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_fields');
    }
};
