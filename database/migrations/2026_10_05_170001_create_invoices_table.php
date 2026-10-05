<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('invoice_number', 64);
            $table->string('payment_model', 32)->default('full_payment'); // full_payment, deposit, no_payment, partial_payment
            $table->unsignedBigInteger('amount_total_idr')->default(0);
            $table->unsignedBigInteger('amount_due_idr')->default(0);
            $table->unsignedBigInteger('amount_paid_idr')->default(0);
            $table->string('status', 32)->default('UNPAID'); // UNPAID, PENDING, PARTIAL, PAID, FAILED, CANCELLED
            $table->dateTime('due_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
