<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standard Laravel notifications table (the `database` channel) — this is what powers
        // in-app notifications for buyers/vendors (see resources/js/Components/NotificationBell.jsx)
        // and, for admins, Filament's own built-in notification bell (see AdminPanelProvider's
        // ->databaseNotifications()), all reading from this one table.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
