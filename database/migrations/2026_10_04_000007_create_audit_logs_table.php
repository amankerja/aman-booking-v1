<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_type')->nullable(); // user, api, system
            $table->string('actor_role')->nullable(); // SUPER_ADMIN, OWNER, MEMBER, CUSTOMER
            $table->string('action'); // created, updated, deleted, status_changed
            $table->string('entity_type'); // Tenant, Business, User, Service, Booking
            $table->string('entity_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('source')->default('web'); // web, api, webhook, scheduled_task
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('created_at')->useCurrent()->index();

            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
