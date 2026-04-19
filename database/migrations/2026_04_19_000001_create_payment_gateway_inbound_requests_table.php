<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_inbound_requests', function (Blueprint $table) {
            $table->id();
            $table->string('plugin');
            $table->string('action');
            $table->unsignedBigInteger('payment_order_id')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->text('handler_exception')->nullable();
            $table->timestamps();

            $table->index(['plugin', 'action', 'created_at']);
            $table->index('payment_order_id');
            $table->foreign('payment_order_id')
                ->references('id')
                ->on('payment_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_inbound_requests');
    }
};
