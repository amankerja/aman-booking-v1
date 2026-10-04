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
        Schema::create('resource_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('icon', 50)->default('Box');
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_space')->default(false);
            $table->boolean('is_equipment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_types');
    }
};
