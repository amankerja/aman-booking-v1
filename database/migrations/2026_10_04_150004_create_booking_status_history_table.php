<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('from_category', 32)->nullable();
            $table->string('to_category', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->string('source', 32)->default('system');
            $table->text('reason')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['booking_id', 'created_at'], 'idx_status_hist_booking_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_history');
    }
};
