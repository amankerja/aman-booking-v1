<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone_e164');
            $table->string('email')->nullable();
            $table->json('tags')->nullable();
            $table->dateTime('marketing_consent_at')->nullable();
            $table->unsignedInteger('no_show_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'phone_e164']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
