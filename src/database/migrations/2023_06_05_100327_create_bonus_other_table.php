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
        Schema::create(DxServiceProvider::DX_PREFIX_TABLE.'bonus_other', function (Blueprint $table) {
            $table->id();
            $table->char('code', 25);
            $table->string('zoho_id', 55)->nullable();
            $table->string('category', 255)->nullable();
            $table->dateTime('date')->nullable();
            $table->string('amount', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('tax', 255)->nullable();
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
        Schema::dropIfExists(DxServiceProvider::DX_PREFIX_TABLE.'bonus_other');
    }
};
