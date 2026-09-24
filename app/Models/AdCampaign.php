<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AdCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'token',
        'banner_path',
        'banner_url',
        'duration_days',
        'starts_at',
        'ends_at',
        'status',
        'paused_at',
        'ending_reminder_sent_at',
        'amount_kobo',
        'payment_reference',
        'paid_at',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'impressions_count',
        'clicks_count',
        'total_dwell_seconds',
        'dwell_samples_count',
    ];

    /**
     * How long a vendor must wait after pausing before they're allowed to resume — see
     * canResume()/resumeAvailableAt() below and AdCampaignService::resume().
     */
    public const RESUME_COOLDOWN_HOURS = 24;

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'vendor_id' => 'integer',
            'duration_days' => 'integer',
            'amount_kobo' => 'integer',
            'approved_by' => 'integer',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'total_dwell_seconds' => 'integer',
            'dwell_samples_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'ending_reminder_sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AdCampaign $campaign) {
            // Opaque, unguessable identifier used in every buyer-facing URL instead of the raw
            // auto-increment id (which would otherwise leak "how many campaigns exist" and let one
            // vendor guess at another's) — see AdController's {adCampaign:token} route bindings.
            $campaign->token ??= Str::random(20);
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Query-level "show these on the homepage/category pages/search results right now" filter —
     * active status AND within its paid-for date window. Being 'active' alone isn't enough since a
     * campaign that already ran its course should stop showing even before the daily ads:expire
     * command gets around to flipping its status.
     */
    public function scopeCurrentlyLive($query)
    {
        $now = Carbon::now();

        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * When a paused campaign becomes resumable again — null once the campaign isn't currently
     * paused (nothing to wait for) or was never paused at all.
     */
    public function resumeAvailableAt(): ?Carbon
    {
        if ($this->status !== 'paused' || ! $this->paused_at) {
            return null;
        }

        return $this->paused_at->copy()->addHours(self::RESUME_COOLDOWN_HOURS);
    }

    public function canResume(): bool
    {
        $availableAt = $this->resumeAvailableAt();

        return $availableAt === null || $availableAt->isPast();
    }

    public function clickThroughRate(): float
    {
        if ($this->impressions_count === 0) {
            return 0.0;
        }

        return round(($this->clicks_count / $this->impressions_count) * 100, 2);
    }

    public function averageDwellSeconds(): int
    {
        if ($this->dwell_samples_count === 0) {
            return 0;
        }

        return (int) round($this->total_dwell_seconds / $this->dwell_samples_count);
    }
}
