<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 2)->nullable()->after('quantity');
            $table->timestamp('posted_at')->nullable()->after('posted');
            $table->foreignUuid('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 2)->default(0);
            $table->decimal('average_cost', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'warehouse_id', 'item_id']);
            $table->index(['company_id', 'item_id']);
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained();
            $table->foreignUuid('item_id')->constrained();
            $table->decimal('quantity_delta', 18, 2);
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('value_delta', 18, 2);
            $table->decimal('balance_quantity', 18, 2);
            $table->decimal('balance_average_cost', 18, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->unique('stock_movement_id');
            $table->index(['company_id', 'warehouse_id', 'item_id', 'created_at'], 'inv_tx_company_warehouse_item_created_idx');
        });

        DB::table('stock_movements')->where('posted', true)->whereNull('posted_at')->update(['posted_at' => now()]);

        foreach (DB::table('items')->where('item_type', 'stock')->where('quantity', '>', 0)->orderBy('id')->cursor() as $item) {
            $warehouseId = DB::table('warehouses')->where('company_id', $item->company_id)->where('active', true)->orderBy('code')->value('id');
            if (! $warehouseId) {
                continue;
            }
            DB::table('inventory_balances')->insert([
                'id' => (string) Str::uuid(), 'company_id' => $item->company_id, 'warehouse_id' => $warehouseId, 'item_id' => $item->id,
                'quantity' => $item->quantity, 'average_cost' => $item->cost, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_balances');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['posted_by']);
            $table->dropColumn(['unit_cost', 'posted_at', 'posted_by']);
        });
    }
};
