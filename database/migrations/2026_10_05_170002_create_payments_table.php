<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('payment_number', 64);
            $table->string('provider', 32)->default('midtrans'); // midtrans, xendit, manual, cash
            $table->string('provider_event_id', 128)->nullable();
            $table->string('provider_transaction_id', 128)->nullable()->index();
            $table->string('payment_method', 32)->default('qris'); // qris, bank_transfer, gopay, shopeepay, credit_card, cash, other
            $table->unsignedBigInteger('amount_idr')->default(0);
            $table->string('status', 32)->default('PENDING'); // PENDING, SETTLEMENT, PAID, FAILED, EXPIRED, REFUNDED
            $table->string('snap_token', 255)->nullable();
            $table->string('checkout_url', 2048)->nullable();
            $table->text('qr_string')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('provider_response')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'payment_number']);
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['tenant_id', 'booking_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
