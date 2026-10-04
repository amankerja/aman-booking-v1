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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->index()->constrained('businesses')->nullOnDelete();
            $table->foreignId('resource_type_id')->constrained('resource_types')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('resource_groups')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->enum('visibility', ['PUBLIC', 'INTERNAL'])->default('PUBLIC');
            $table->enum('state', ['AVAILABLE', 'BLOCKED', 'MAINTENANCE', 'INACTIVE'])->default('AVAILABLE');
            $table->json('skills')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'resource_type_id', 'state']);
            $table->index(['tenant_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
