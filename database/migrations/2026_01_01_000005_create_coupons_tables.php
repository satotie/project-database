<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->unsignedTinyInteger('discount_percent');
            $table->boolean('applies_to_all')->default(false);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE coupons ADD CONSTRAINT chk_coupons_discount_percent CHECK (discount_percent BETWEEN 1 AND 100)');
        }

        // M:N — a coupon applies to many products, a product can be in many coupons.
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['coupon_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_product');
        Schema::dropIfExists('coupons');
    }
};
