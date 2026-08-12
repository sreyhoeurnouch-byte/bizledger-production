<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignUuid('destination_warehouse_id')->nullable()->after('warehouse_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('reversal_of_id')->nullable()->after('posted_by')->constrained('stock_movements')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable()->after('reversal_of_id');
            $table->unique('reversal_of_id');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index('stock_movement_id', 'inv_tx_stock_movement_idx');
            $table->dropUnique('inventory_transactions_stock_movement_id_unique');
            $table->unique(['stock_movement_id', 'warehouse_id'], 'inv_tx_movement_warehouse_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropUnique('inv_tx_movement_warehouse_unique');
            $table->unique('stock_movement_id');
            $table->dropIndex('inv_tx_stock_movement_idx');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('stock_movements_reversal_of_id_unique');
            $table->dropForeign(['destination_warehouse_id', 'reversal_of_id']);
            $table->dropColumn(['destination_warehouse_id', 'reversal_of_id', 'reversal_reason']);
        });
    }
};
