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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event', 50); // BOOKING_CREATED, BOOKING_CONFIRMED, BOOKING_RESCHEDULED, BOOKING_CANCELLED, BOOKING_REMINDER_H1
            $table->string('channel', 20); // EMAIL, WHATSAPP
            $table->string('name', 100);
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'event', 'channel'], 'notif_tpl_tenant_event_channel_unique');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
