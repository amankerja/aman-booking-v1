<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('code')->unique(); // BASIC, PRO, BUSINESS
            $table->string('name');
            $table->unsignedBigInteger('price_idr')->default(0); // Bigint Rupiah (PRD Rule 6)
            $table->string('billing_cycle')->default('MONTHLY'); // MONTHLY, YEARLY
            $table->json('limits'); // max_businesses, max_members, max_services, max_resources, max_monthly_bookings
            $table->json('features'); // whatsapp_notifications, online_payments, inventory, etc.
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
