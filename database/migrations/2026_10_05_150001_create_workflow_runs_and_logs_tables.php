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
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('workflow_id')->index();
            $table->unsignedBigInteger('version_id')->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->string('execution_id', 64)->unique();
            $table->string('trigger_event', 64);
            $table->json('trigger_payload')->nullable();
            $table->string('status', 32)->default('PENDING'); // PENDING, RUNNING, COMPLETED, FAILED, CANCELLED
            $table->string('current_node_id', 64)->nullable();
            $table->unsignedInteger('depth')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'booking_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->foreign('version_id')->references('id')->on('workflow_versions')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
        });

        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('workflow_run_id')->index();
            $table->string('node_id', 64);
            $table->string('node_type', 32); // trigger, action, condition, delay
            $table->string('node_label', 128)->nullable();
            $table->string('status', 32)->default('PENDING'); // PENDING, RUNNING, SUCCESS, FAILED, SKIPPED, WAITING_DELAY
            $table->unsignedInteger('attempt')->default(1);
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('run_at')->nullable(); // For delayed execution
            $table->dateTime('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_run_id', 'node_id', 'attempt'], 'wf_logs_run_node_attempt_unique');
            $table->index(['tenant_id', 'workflow_run_id']);
            $table->index(['status', 'run_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('workflow_run_id')->references('id')->on('workflow_runs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_logs');
        Schema::dropIfExists('workflow_runs');
    }
};
