<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->json('customer_data')->nullable(); // Additional customer information
            $table->text('description')->nullable();
            $table->text('success_callback')->nullable(); // PHP code to execute on success
            $table->text('failure_callback')->nullable(); // PHP code to execute on failure
            $table->string('success_url')->nullable(); // URL to redirect after success
            $table->string('failure_url')->nullable(); // URL to redirect after failure
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('external_transaction_id')->nullable();
            $table->json('payment_data')->nullable(); // Additional payment information from gateway
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('order_code');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
}; 