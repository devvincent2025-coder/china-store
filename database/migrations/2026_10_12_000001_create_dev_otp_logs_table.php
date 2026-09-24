<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dev-only convenience — see App\Support\Dev\OtpLogger, which is the only thing that ever writes
 * to this table (and only when app()->environment('local')), and routes/dev.php's /dev/otps page,
 * the only thing that ever reads it. Lets a codes be copy-pasted straight from a browser tab
 * instead of digging through storage/logs/laravel.log whenever the real SMS/email gateway isn't
 * available in local development.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_otp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('purpose');
            $table->string('identifier');
            $table->string('code');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_otp_logs');
    }
};
