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
        // 1. Inventory Items (PRD 17, 214)
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('sku', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('unit', 30)->default('pcs');
            $table->unsignedBigInteger('cost_price_idr')->default(0);
            $table->unsignedBigInteger('sale_price_idr')->default(0);
            $table->integer('initial_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'business_id']);
            $table->unique(['tenant_id', 'sku']);
        });

        // 2. Inventory Movements / Mutations (PRD 17.2, 214)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('type', 30); // OPENING, PURCHASE, ADJUSTMENT_IN, ADJUSTMENT_OUT, CONSUMED_BY_BOOKING, SALE, RETURN, WASTE
            $table->integer('quantity'); // Positive or absolute delta
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->unsignedBigInteger('unit_cost_idr')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'inventory_item_id', 'created_at'], 'inv_mov_tenant_item_created_idx');
            $table->index(['tenant_id', 'booking_id'], 'inv_mov_tenant_booking_idx');
            $table->index(['tenant_id', 'type'], 'inv_mov_tenant_type_idx');
        });

        // 3. Service Inventory Mapping (PRD 17.3, 112-121)
        Schema::create('service_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->string('deduction_mode', 30)->nullable(); // RESERVE_ON_BOOKING, DEDUCT_ON_SERVICE, DEDUCT_ON_COMPLETE
            $table->timestamps();

            $table->index(['service_id', 'inventory_item_id']);
            $table->index(['tenant_id', 'service_id']);
        });

        // 4. Booking Inventory Items / Reservations (PRD 17.3)
        Schema::create('booking_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->string('mode', 30); // RESERVE_ON_BOOKING, DEDUCT_ON_SERVICE, DEDUCT_ON_COMPLETE
            $table->string('status', 30)->default('RESERVED'); // RESERVED, CONSUMED, RELEASED
            $table->timestamps();

            $table->index(['tenant_id', 'booking_id'], 'bkg_inv_tenant_booking_idx');
            $table->index(['inventory_item_id', 'status'], 'bkg_inv_item_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_inventory_items');
        Schema::dropIfExists('service_inventory_items');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
