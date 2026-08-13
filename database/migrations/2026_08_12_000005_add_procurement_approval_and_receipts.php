<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignUuid('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('received_quantity', 18, 2)->default(0)->after('quantity');
        });
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained();
            $table->string('number', 40);
            $table->date('receipt_date');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->useCurrent();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'purchase_order_id']);
        });
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained();
            $table->decimal('quantity', 18, 2);
            $table->decimal('unit_cost', 18, 2);
            $table->foreignUuid('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['goods_receipt_id', 'purchase_order_line_id'], 'gr_line_receipt_po_line_unique');
            $table->unique('stock_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::table('purchase_order_lines', fn (Blueprint $table) => $table->dropColumn('received_quantity'));
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_at', 'approved_by']);
        });
    }
};
