<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price_idr', 14, 2)->default(0);
            $table->string('duration_type')->default('FIXED'); // FIXED, PER_QUANTITY, PER_UNIT_SIZE, VARIABLE
            $table->integer('duration_minutes')->default(60);
            $table->json('duration_rule')->nullable();
            $table->integer('buffer_before')->default(0);
            $table->integer('buffer_after')->default(0);
            $table->integer('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->json('rules')->nullable();
            $table->dateTime('archived_at')->nullable()->index();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable()->index();

            $table->index(['tenant_id', 'archived_at']);
            $table->index(['business_id', 'is_active']);
            $table->unique(['business_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
