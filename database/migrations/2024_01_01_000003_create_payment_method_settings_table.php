<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_method_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false); // For sensitive data like API keys
            $table->timestamps();

            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
            $table->unique(['payment_method_id', 'key']);
            $table->index('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_settings');
    }
};
