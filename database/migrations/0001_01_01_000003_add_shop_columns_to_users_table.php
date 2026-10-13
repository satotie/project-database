<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // EER specialization User -> {Customer, Staff} mapped to a single table with a type attribute.
            $table->enum('role', ['customer', 'staff'])->default('customer')->after('password');
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};
