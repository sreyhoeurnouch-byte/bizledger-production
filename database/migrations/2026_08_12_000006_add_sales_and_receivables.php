<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('contacts');
            $table->string('number', 40);
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->enum('status', ['draft', 'approved', 'partially_delivered', 'delivered', 'invoiced', 'closed', 'cancelled'])->default('draft');
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id', 'status']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained();
            $table->unsignedSmallInteger('line_number');
            $table->decimal('quantity', 18, 2);
            $table->decimal('delivered_quantity', 18, 2)->default(0);
            $table->decimal('invoiced_quantity', 18, 2)->default(0);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
            $table->unique(['sales_order_id', 'line_number']);
            $table->index(['company_id', 'item_id']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained();
            $table->string('number', 40);
            $table->date('delivery_date');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->useCurrent();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'sales_order_id']);
        });

        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sales_order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained();
            $table->decimal('quantity', 18, 2);
            $table->foreignUuid('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['delivery_id', 'sales_order_line_id']);
            $table->unique('stock_movement_id');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('contacts');
            $table->foreignUuid('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'paid', 'void'])->default('open');
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id', 'status', 'due_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sales_order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained();
            $table->unsignedSmallInteger('line_number');
            $table->decimal('quantity', 18, 2);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
            $table->unique(['invoice_id', 'line_number']);
            $table->index(['company_id', 'sales_order_line_id']);
        });

        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('contacts');
            $table->string('number', 40);
            $table->date('receipt_date');
            $table->decimal('amount', 18, 2);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id', 'receipt_date']);
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['customer_receipt_id', 'invoice_id']);
            $table->index(['company_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('customer_receipts');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('delivery_lines');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }
};
