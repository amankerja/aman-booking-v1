<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->default('09:00:00');
            $table->time('close_time')->default('17:00:00');
            $table->json('breaks')->nullable(); // Array of [{ name, start_time, end_time }]
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['business_id', 'day_of_week']);
            $table->index(['tenant_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
