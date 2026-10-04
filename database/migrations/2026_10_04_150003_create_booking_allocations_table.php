<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_allocations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->string('role', 32)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 32)->default('ACTIVE');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'resource_id', 'start_at', 'end_at'], 'idx_alloc_res_window');
            $table->index(['booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_allocations');
    }
};
