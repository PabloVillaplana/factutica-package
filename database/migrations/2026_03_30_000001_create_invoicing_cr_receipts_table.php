<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_cr_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_type', 10);
            $table->string('ui_key', 50)->unique()->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->unsignedSmallInteger('establishment')->default(1);
            $table->unsignedMediumInteger('terminal')->default(1);
            $table->string('consecutive_number', 20);
            $table->dateTime('emission_date');
            $table->dateTime('sent_to_hacienda_at')->nullable();
            $table->string('receipt_status', 20)->default('pending');
            $table->string('hacienda_status', 20)->default('pending');
            $table->longText('signed_xml')->nullable();
            $table->string('sell_condition', 10);
            $table->decimal('total_amount', 18, 5);
            $table->decimal('tax_amount', 18, 5)->default(0);
            $table->decimal('total_discount', 18, 5)->default(0);
            $table->decimal('total_voucher', 18, 5);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 10, 5)->nullable();
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
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_cr_receipts');
    }
};