<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lets an admin lock a buyer or vendor account out of the platform without deleting it —
        // enforced at login (see AuthenticatedSessionController::store()) and on every subsequent
        // request (see App\Http\Middleware\EnsureUserIsNotSuspended).
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('password');
            $table->string('suspension_reason')->nullable()->after('is_suspended');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_suspended', 'suspension_reason', 'suspended_at']);
        });
    }
};
