<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50);
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->json('service_snapshot');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('business_timezone', 64)->default('Asia/Jakarta');
            $table->string('status_category', 32)->default('CONFIRMED');
            $table->string('status_id', 64)->nullable();
            $table->string('payment_status', 32)->default('UNPAID');
            $table->unsignedBigInteger('total_idr')->default(0);
            $table->unsignedBigInteger('deposit_idr')->default(0);
            $table->string('source', 32)->default('PUBLIC');
            $table->dateTime('hold_expires_at')->nullable();
            $table->unsignedInteger('reschedule_count')->default(0);
            $table->string('manage_token', 128)->nullable()->index();
            $table->dateTime('manage_token_expires_at')->nullable();
            $table->unsignedBigInteger('workflow_version_id')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'start_at']);
            $table->index(['tenant_id', 'status_category', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
