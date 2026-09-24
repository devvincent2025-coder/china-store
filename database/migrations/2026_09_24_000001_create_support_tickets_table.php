<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One helpdesk for both buyers and vendors — the same User model covers both, and the
        // support page (resources/js/Pages/Support/*.jsx) simply renders inside whichever layout
        // matches the user's role. Admin triages everything from one Filament resource.
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->enum('category', ['order', 'payment', 'product', 'account', 'other'])->default('other');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->enum('status', ['open', 'pending', 'resolved', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
