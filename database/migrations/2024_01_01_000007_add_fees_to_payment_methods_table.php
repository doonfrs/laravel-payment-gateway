<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->decimal('fee_percentage', 5, 2)->nullable()->after('sort_order');
            $table->decimal('fee_fixed_amount', 10, 2)->nullable()->after('fee_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['fee_percentage', 'fee_fixed_amount']);
        });
    }
};
