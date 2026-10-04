<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('tags');
            $table->boolean('is_verified')->default(false)->after('no_show_count');
            $table->json('metadata')->nullable()->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['notes', 'is_verified', 'metadata']);
        });
    }
};
