<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resource_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday, 1=Monday, ..., 6=Saturday
            $table->boolean('is_available')->default(true);
            $table->time('start_time')->default('09:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->json('breaks')->nullable(); // [{"start": "12:00", "end": "13:00", "title": "Break"}]
            $table->timestamps();

            $table->unique(['resource_id', 'day_of_week']);
            $table->index(['tenant_id', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_schedules');
    }
};
