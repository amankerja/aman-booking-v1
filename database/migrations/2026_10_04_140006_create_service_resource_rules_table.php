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
        Schema::create('service_resource_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('resource_type_id')->nullable()->constrained('resource_types')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('resource_groups')->nullOnDelete();
            $table->json('required_skills')->nullable();
            $table->boolean('is_required')->default(true);
            $table->enum('assignment_mode', ['CUSTOMER_CHOICE', 'AUTO_ASSIGN', 'OWNER_ASSIGN', 'POOL'])->default('AUTO_ASSIGN');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['service_id']);
            $table->index(['tenant_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_resource_rules');
    }
};
