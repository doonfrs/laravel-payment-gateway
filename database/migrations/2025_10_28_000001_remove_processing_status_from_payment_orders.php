<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Convert any existing 'processing' orders to 'failed'
        // These are abandoned orders that were stuck in processing status
        DB::table('payment_orders')
            ->where('status', 'processing')
            ->whereNull('paid_at')
            ->update(['status' => 'failed']);

        // Step 2: If using MySQL, modify the enum to remove 'processing'
        // Note: This is a destructive change - ensure no orders remain in processing status
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_orders MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add 'processing' back to the enum
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_orders MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        // Note: We don't convert failed orders back to processing as we can't determine which ones were originally processing
    }
};
