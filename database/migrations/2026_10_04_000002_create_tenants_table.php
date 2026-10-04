<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('status')->default('ACTIVE')->index(); // ACTIVE, SUSPENDED, CANCELLED
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
