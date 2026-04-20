<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_cr_economic_activities', function (Blueprint $table) {
            $table->string('codigo', 6)->primary();
            $table->text('descripcion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_cr_economic_activities');
    }
};