<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_cr_hacienda_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('invoicing_cr_receipts')->cascadeOnDelete();
            $table->string('receipt_key', 50)->unique();
            $table->string('hacienda_status', 20);
            $table->text('response_xml')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_cr_hacienda_responses');
    }
};