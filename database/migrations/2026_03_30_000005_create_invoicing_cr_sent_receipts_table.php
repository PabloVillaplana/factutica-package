<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_cr_sent_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_type', 10);
            $table->string('ui_key', 50)->unique()->nullable();
            $table->string('consecutive_number', 20);
            $table->dateTime('emission_date');
            $table->dateTime('sent_to_hacienda_at')->nullable();
            $table->string('receipt_status', 20)->default('pending');
            $table->string('hacienda_status', 20)->default('pending');
            $table->longText('signed_xml')->nullable();
            $table->string('reception_message')->nullable();
            $table->string('reception_status', 30)->default('pending');
            $table->string('reception_code', 10)->nullable();
            $table->string('economic_activity_code', 10)->nullable();
            $table->string('tax_condition_code', 10)->nullable();
            $table->decimal('tax_credited', 18, 5)->nullable();
            $table->decimal('total_expense', 18, 5)->nullable();
            $table->decimal('tax_amount', 18, 5)->nullable();
            $table->decimal('total_voucher', 18, 5);
            $table->string('issuer_name');
            $table->string('issuer_number', 20);
            $table->string('issuer_identification_type', 2);
            $table->string('receiver_name')->nullable();
            $table->string('receiver_number', 20)->nullable();
            $table->string('receiver_identification_type', 2)->nullable();
            $table->timestamps();

            $table->index('consecutive_number');
            $table->index('hacienda_status');
            $table->index('receipt_status');
            $table->index('ui_key');
            $table->index('receipt_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_cr_sent_receipts');
    }
};