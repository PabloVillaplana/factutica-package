<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_cr_receipt_consecutives', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_type', 10);
            $table->unsignedSmallInteger('establishment')->default(1);
            $table->unsignedMediumInteger('terminal')->default(1);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['receipt_type', 'establishment', 'terminal'], 'consecutives_type_est_term_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_cr_receipt_consecutives');
    }
};