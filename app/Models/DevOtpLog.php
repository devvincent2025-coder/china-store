<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dev-only OTP mirror — see App\Support\Dev\OtpLogger for the guard that keeps this from ever
 * being written to outside a local environment. `created_at` only (no `updated_at`); a row is
 * never modified after it's written, only read from routes/dev.php's /dev/otps page.
 */
class DevOtpLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purpose',
        'identifier',
        'code',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
