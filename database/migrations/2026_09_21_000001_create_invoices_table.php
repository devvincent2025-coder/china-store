<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A vendor-generated invoice for an out-of-website sale (phone order, in-person, social
        // media DM, etc). Unlike Order/VendorOrder — which are created automatically from a cart —
        // an Invoice is authored manually by the vendor and the "customer" may not even have a
        // platform account, so customer details are captured as plain columns rather than a
        // user_id foreign key.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            $table->string('invoice_number')->unique();

            // Unguessable token used for the public, unauthenticated payment link sent to the
            // customer — never expose the numeric id for that purpose, since ids are sequential
            // and would let one customer probe another vendor's invoices.
            $table->uuid('public_token')->unique();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();

            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('subtotal')->default(0)->comment('Kobo, sum of line items');
            $table->unsignedBigInteger('discount')->default(0)->comment('Kobo, flat discount vendor applied');
            $table->unsignedBigInteger('vat_amount')->default(0)->comment('Kobo');
            $table->unsignedBigInteger('shipping_amount')->default(0)->comment('Kobo');
            $table->unsignedBigInteger('total')->default(0)->comment('Kobo, (subtotal - discount) + vat + shipping');
            $table->unsignedBigInteger('amount_paid')->default(0)->comment('Kobo, sum of successful invoice_payments');

            // draft: vendor is still editing, not yet visible to the customer.
            // sent: emailed to the customer, awaiting payment.
            // partially_paid / paid: derived from amount_paid vs total, kept as a column too so the
            // vendor invoice list can filter/sort on status without recomputing on every row.
            // void: vendor cancelled it before payment.
            $table->enum('status', ['draft', 'sent', 'partially_paid', 'paid', 'void'])->default('draft');

            $table->boolean('allow_partial_payment')->default(false);
            $table->unsignedBigInteger('minimum_partial_amount')->nullable()->comment('Kobo, floor for one partial payment');

            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
