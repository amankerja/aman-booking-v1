<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global workflow & form templates with immutable versions (PRD 47, 185, 186).
 *
 * These tables are platform-wide (no tenant_id). Tenants receive a COPY of a
 * template version at install time; the copy records its origin so that a
 * newer template version can be offered as an opt-in update, never an overwrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_templates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('kind', 20); // workflow | form
            $table->string('key', 64);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('business_type', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('latest_version_id')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'key']);
            $table->index(['kind', 'is_active']);
        });

        Schema::create('system_template_versions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('system_template_id')->constrained('system_templates')->cascadeOnDelete();
            $table->string('version', 20); // semver, e.g. 1.0.0
            $table->string('status', 20)->default('DRAFT'); // DRAFT | PUBLISHED
            $table->text('changelog')->nullable();
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['system_template_id', 'version']);
            $table->index(['system_template_id', 'status']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->unsignedBigInteger('source_template_id')->nullable()->after('current_version_id');
            $table->unsignedBigInteger('source_template_version_id')->nullable()->after('source_template_id');
            $table->index(['tenant_id', 'source_template_id']);
        });

        Schema::table('booking_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('source_template_id')->nullable()->after('is_active');
            $table->unsignedBigInteger('source_template_version_id')->nullable()->after('source_template_id');
            $table->index(['tenant_id', 'source_template_id']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_forms', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source_template_id']);
            $table->dropColumn(['source_template_id', 'source_template_version_id']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source_template_id']);
            $table->dropColumn(['source_template_id', 'source_template_version_id']);
        });

        Schema::dropIfExists('system_template_versions');
        Schema::dropIfExists('system_templates');
    }
};
