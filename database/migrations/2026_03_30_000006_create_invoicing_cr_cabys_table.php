<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_cr_cabys', function (Blueprint $table) {
            $table->string('codigo', 13)->primary();
            $table->text('descripcion');
            $table->decimal('impuesto', 5, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_cr_cabys');
    }
};