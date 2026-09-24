<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('line1');
            $table->string('city');
            $table->string('state');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Buyers who already saved a single default address (the old single-slot feature on the
        // Profile page) get it carried over as their first address-book entry, so they never see
        // an empty address book at checkout the first time this ships.
        DB::table('users')
            ->whereNotNull('default_shipping_address')
            ->select('id', 'default_shipping_address')
            ->orderBy('id')
            ->each(function ($user) {
                $address = json_decode($user->default_shipping_address, true);

                if (! empty($address['line1'])) {
                    DB::table('shipping_addresses')->insert([
                        'user_id' => $user->id,
                        'name' => $address['name'] ?? '',
                        'phone' => $address['phone'] ?? '',
                        'line1' => $address['line1'] ?? '',
                        'city' => $address['city'] ?? '',
                        'state' => $address['state'] ?? '',
                        'is_default' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_addresses');
    }
};
