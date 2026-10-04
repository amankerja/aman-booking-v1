<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
            $table->string('whatsapp')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');
            $table->json('policies')->nullable()->after('settings');
            $table->json('booking_rules')->nullable()->after('policies');
            $table->json('social_links')->nullable()->after('booking_rules');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'whatsapp',
                'city',
                'province',
                'postal_code',
                'policies',
                'booking_rules',
                'social_links',
            ]);
        });
    }
};
