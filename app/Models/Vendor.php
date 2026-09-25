<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Vendor extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'business_name',
        'contact_email',
        'contact_phone',
        'verification_documents',
        'documents_status',
        'documents_rejection_reason',
        'documents_reviewed_by',
        'documents_reviewed_at',
        'bank_code',
        'bank_name',
        'account_number',
        'resolved_account_name',
        'bank_verification_status',
        'bank_verified_at',
        'bank_verification_response',
        'korapay_subaccount_id',
        'korapay_subaccount_created_at',
        'pending_bank_code',
        'pending_bank_name',
        'pending_account_number',
        'pending_resolved_account_name',
        'bank_change_status',
        'bank_change_otp_hash',
        'bank_change_otp_expires_at',
        'bank_change_requested_at',
        'bank_change_reviewed_by',
        'bank_change_reviewed_at',
        'bank_change_rejection_reason',
        'identity_document_path',
        'identity_document_type',
        'identity_verification_status',
        'identity_rejection_reason',
        'identity_reviewed_by',
        'identity_reviewed_at',
        'kyc_celebration_seen_at',
        'phone_verified_at',
        'phone_otp_hash',
        'phone_otp_expires_at',
        'commission_rate',
        'shipping_fee_type',
        'shipping_fee_fixed_amount',
        'shipping_fee_percentage',
        'status',
        'suspension_reason',
        'suspended_at',
        'suspended_by',
        'vacation_mode',
        'vacation_message',
        'low_stock_threshold',
        'delivery_days_min',
        'delivery_days_max',
        'notify_new_order_email',
        'notify_new_order_push',
        'auto_publish_products',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'user_id' => 'integer',
            'documents_reviewed_by' => 'integer',
            'bank_change_reviewed_by' => 'integer',
            'identity_reviewed_by' => 'integer',
            'shipping_fee_fixed_amount' => 'integer',
            'suspended_by' => 'integer',
            'low_stock_threshold' => 'integer',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'verification_documents' => 'array',
            'bank_verification_response' => 'array',
            'documents_reviewed_at' => 'datetime',
            'bank_verified_at' => 'datetime',
            'korapay_subaccount_created_at' => 'datetime',
            'bank_change_otp_expires_at' => 'datetime',
            'bank_change_requested_at' => 'datetime',
            'bank_change_reviewed_at' => 'datetime',
            'identity_reviewed_at' => 'datetime',
            'kyc_celebration_seen_at' => 'datetime',
            'suspended_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_otp_expires_at' => 'datetime',
            'commission_rate' => 'decimal:2',
            'shipping_fee_percentage' => 'decimal:2',
            'vacation_mode' => 'boolean',
            'notify_new_order_email' => 'boolean',
            'notify_new_order_push' => 'boolean',
            'auto_publish_products' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): HasOne
    {
        return $this->hasOne(VendorStore::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(VendorReport::class);
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function bankChangeReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bank_change_reviewed_by');
    }

    public function documentsReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'documents_reviewed_by');
    }

    /**
     * True once both onboarding gates have passed — the manual document check and the automated
     * bank verification — meaning it's safe to provision the Korapay subaccount. This is checked
     * BEFORE the subaccount exists, so it must not itself depend on korapay_subaccount_id.
     */
    public function hasPassedOnboardingChecks(): bool
    {
        return $this->documents_status === 'approved'
            && $this->bank_verification_status === 'verified';
    }

    /**
     * Identity/KYC verification — deliberately independent of onboarding (hasPassedOnboardingChecks
     * above): a vendor can go live and start selling without this, but EscrowService::release()
     * requires it before any payout actually leaves the platform. Three checks: the vendor's own
     * login email is verified (User::hasVerifiedEmail(), now functional — see User::class), their
     * phone number is verified via OTP, and they've uploaded a passport/ID that an admin approved.
     */
    public function hasCompletedKyc(): bool
    {
        return $this->user->hasVerifiedEmail()
            && $this->phone_verified_at !== null
            && $this->identity_verification_status === 'approved';
    }

    public function identityReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_reviewed_by');
    }

    /**
     * True exactly once — the vendor dashboard shows a celebratory "you're a verified seller"
     * modal the first time they load it after admin approval, then this flips false forever once
     * IdentityController::markCelebrationSeen() records it. The approval itself happens in an
     * admin's own session, so a normal Inertia flash can't carry the "just approved" signal across
     * to the vendor's next visit — this column is what makes it durable instead.
     */
    public function shouldShowKycCelebration(): bool
    {
        return $this->identity_verification_status === 'approved' && $this->kyc_celebration_seen_at === null;
    }

    public function routeNotificationForMail(): string
    {
        return $this->contact_email;
    }

    /**
     * VAT applied to the post-discount subtotal, at the single platform-wide rate an admin sets in
     * Filament\Pages\Settings (App\Models\Setting::vatRate()) — NOT something an individual vendor
     * configures (previously vendors.vat_rate let each vendor pick their own, inconsistently). Uses
     * basis-point integer math (rate × 100) rather than floating point so a rate like 7.50% never
     * drifts by a kobo on rounding — see App\Services\Checkout\CheckoutService for where this is used.
     */
    public function calculateVat(int $amountKobo): int
    {
        return $this->applyPercentage($amountKobo, \App\Models\Setting::vatRate());
    }

    /**
     * Shipping fee per this vendor's own configuration: a flat kobo amount, a percentage of the
     * (post-discount) subtotal, or both added together.
     */
    public function calculateShippingFee(int $subtotalKobo): int
    {
        return match ($this->shipping_fee_type) {
            'fixed' => $this->shipping_fee_fixed_amount,
            'percentage' => $this->applyPercentage($subtotalKobo, (float) $this->shipping_fee_percentage),
            'both' => $this->shipping_fee_fixed_amount + $this->applyPercentage($subtotalKobo, (float) $this->shipping_fee_percentage),
            default => 0,
        };
    }

    private function applyPercentage(int $amountKobo, float $percentage): int
    {
        $basisPoints = (int) round($percentage * 100);

        return intdiv($amountKobo * $basisPoints, 10000);
    }
}
