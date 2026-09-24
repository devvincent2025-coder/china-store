<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A ledger of every Korapay charge attempt against an invoice — an invoice can be paid in
        // several installments (full or partial payment), so this is one row per attempt rather
        // than a single payment_status column on the invoice itself.
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('reference')->unique()->comment('Korapay charge reference');
            $table->unsignedBigInteger('amount')->comment('Kobo, amount attempted for this charge');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');

            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
