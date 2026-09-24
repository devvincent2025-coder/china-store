<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            // One user owns exactly one vendor account; the buyer/vendor distinction is enforced via
            // the spatie/laravel-permission role assigned to the owning user, not a separate table.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('business_name');
            $table->string('contact_email');
            $table->string('contact_phone');

            // Identity/business verification documents (ID, CAC certificate, etc). Stored as a JSON
            // array of Supabase Storage PATHS (not URLs) in the private bucket — documents are
            // sensitive, so they're only ever accessed via a short-lived signed URL generated on
            // demand (see SupabaseStorageService::signedUrl()), never a permanent public link.
            // This is the ONE manual checkpoint in the whole onboarding flow.
            $table->json('verification_documents')->nullable();
            $table->enum('documents_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('documents_rejection_reason')->nullable();
            $table->foreignId('documents_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('documents_reviewed_at')->nullable();

            // Payout bank details entered by the vendor, verified automatically via Korapay's
            // resolve-account endpoint (see App\Services\Korapay\KorapayService::resolveBankAccount()).
            $table->string('bank_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('resolved_account_name')->nullable();
            $table->enum('bank_verification_status', ['pending', 'verified', 'failed'])->default('pending');
            $table->timestamp('bank_verified_at')->nullable();
            // Raw Korapay resolve-account response, kept for audit purposes as requested.
            $table->json('bank_verification_response')->nullable();

            // Populated once bank verification AND document approval both succeed — this is what lets
            // Korapay Split Payments route funds directly to the vendor with no manual dashboard work.
            $table->string('korapay_subaccount_id')->nullable();
            $table->timestamp('korapay_subaccount_created_at')->nullable();
            // Platform commission percentage taken out of this vendor's sales via the split payment.
            $table->decimal('commission_rate', 5, 2)->default(10.00);

            // VAT this vendor charges on their own goods (0-20%, vendor-configurable from their
            // dashboard) — collected from the buyer alongside the sale and paid out to the vendor in
            // full (not part of the platform's commission), since remitting it is the vendor's own
            // tax obligation, not the platform's.
            $table->decimal('vat_rate', 5, 2)->default(0.00);

            // Shipping fee: a flat kobo amount, a percentage of the (post-discount) order subtotal,
            // or both added together — vendor's choice, configurable from their dashboard. Like VAT,
            // the full shipping amount is paid out to the vendor (they handle their own fulfillment).
            $table->enum('shipping_fee_type', ['fixed', 'percentage', 'both'])->default('fixed');
            $table->unsignedBigInteger('shipping_fee_fixed_amount')->default(0)->comment('Kobo');
            $table->decimal('shipping_fee_percentage', 5, 2)->default(0.00);

            // Overall lifecycle: pending (just signed up) -> active (fully approved, can sell) ->
            // suspended (admin can pull a vendor offline without deleting their data).
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
