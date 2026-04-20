<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_cr_receipt_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('invoicing_cr_receipts')->cascadeOnDelete();
            $table->string('receipt_type', 10);
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_cr_receipt_payloads');
    }
};