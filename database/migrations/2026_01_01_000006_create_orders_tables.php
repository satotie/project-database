<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['PENDING_PAYMENT', 'PAID', 'PREPARING', 'SHIPPED', 'DELIVERED', 'CANCELLED'])
                ->default('PENDING_PAYMENT')->index();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2);
            $table->decimal('total', 10, 2);
            $table->enum('shipping_method', ['STANDARD', 'EXPRESS', 'PICKUP']);
            // Shipping snapshot (not an FK) so the order keeps the address used at purchase time.
            $table->string('ship_name');
            $table->string('ship_phone', 20);
            $table->text('ship_address')->nullable();
            $table->string('tracking_no')->nullable();
            $table->string('note')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            // Snapshots of the product at purchase time.
            $table->string('product_name');
            $table->string('size', 10);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('slip_path');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
