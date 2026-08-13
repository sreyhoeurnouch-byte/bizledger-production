<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL auto-commits DDL. This makes a retry safe if an earlier deployment
        // created the tables before failing on an index declaration.
        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_payment_allocations', function (Blueprint $table) {
                $table->unique(['vendor_payment_id', 'vendor_bill_id'], 'vendor_payment_bill_unique');
            });

            return;
        }

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('contacts');
            $table->foreignUuid('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'paid', 'void'])->default('open');
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'vendor_id', 'status', 'due_date']);
        });

        Schema::create('vendor_bill_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('description', 255);
            $table->decimal('quantity', 18, 2);
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
            $table->unique(['vendor_bill_id', 'line_number']);
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('contacts');
            $table->string('number', 40);
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'vendor_id', 'payment_date']);
        });

        Schema::create('vendor_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vendor_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['vendor_payment_id', 'vendor_bill_id'], 'vendor_payment_bill_unique');
            $table->index(['company_id', 'vendor_bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_allocations');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_bill_lines');
        Schema::dropIfExists('vendor_bills');
    }
};
