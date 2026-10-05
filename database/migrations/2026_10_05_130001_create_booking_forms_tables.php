<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Booking Forms (PRD 26, 214)
        Schema::create('booking_forms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'service_id', 'is_active']);
        });

        // 2. Booking Form Fields (PRD 26, 27)
        Schema::create('booking_form_fields', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('booking_forms')->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->string('type', 32); // text, textarea, number, currency, phone, email, date, time, select, multiselect, radio, checkbox, file, address
            $table->string('label', 150);
            $table->string('placeholder', 150)->nullable();
            $table->string('help_text', 255)->nullable();
            $table->boolean('is_required')->default(false);
            $table->text('default_value')->nullable();
            $table->json('options')->nullable(); // For select, radio, checkbox, multiselect
            $table->json('validation_rules')->nullable(); // min, max, mimes, max_size_kb, etc.
            $table->json('visibility_conditions')->nullable(); // conditional rules: { field, operator, value }
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'field_key']);
            $table->index(['tenant_id', 'form_id', 'sort_order']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        // 3. Booking Custom Fields Storage (PRD 214)
        Schema::create('booking_custom_fields', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->string('field_label', 150);
            $table->string('field_type', 32);
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'field_key']);
            $table->index(['tenant_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_custom_fields');
        Schema::dropIfExists('booking_form_fields');
        Schema::dropIfExists('booking_forms');
    }
};
