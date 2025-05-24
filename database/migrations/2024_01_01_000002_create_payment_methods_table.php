<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plugin_class'); // The class name of the payment plugin
            $table->boolean('enabled')->default(true);
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
