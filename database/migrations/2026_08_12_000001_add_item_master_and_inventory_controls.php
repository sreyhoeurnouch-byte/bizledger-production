<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->enum('item_type', ['stock', 'service'])->default('stock')->after('name');
            $table->boolean('for_purchase')->default(true)->after('item_type');
            $table->boolean('for_sale')->default(true)->after('for_purchase');
            $table->unique(['company_id', 'barcode']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('adjustment_direction', ['increase', 'decrease'])->nullable()->after('kind');
            $table->index(['company_id', 'item_id', 'posted']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'item_id', 'posted']);
            $table->dropColumn('adjustment_direction');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'barcode']);
            $table->dropColumn(['item_type', 'for_purchase', 'for_sale']);
        });
    }
};
