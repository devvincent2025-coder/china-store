<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminActivityLog extends Model
{
    protected $fillable = [
        'admin_user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'admin_user_id' => 'integer',
            'subject_id' => 'integer',
        ];
    }

    /**
     * Records one admin action. Called from Filament resource/page actions rather than a generic
     * model observer, since not every change to these models is admin-initiated (e.g. a webhook
     * updating escrow_status is not something an admin "did").
     */
    public static function record(string $action, ?Model $subject, string $description): void
    {
        self::create([
            'admin_user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
        ]);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
