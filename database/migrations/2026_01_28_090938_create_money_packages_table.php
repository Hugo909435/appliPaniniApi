<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Pack Starter, Pack Pro, Pack Ultimate
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('amount'); // Quantité de money reçue
            $table->integer('price'); // Prix en centimes (ex: 999 = 9.99€)
            $table->string('currency')->default('eur');
            $table->integer('bonus')->default(0); // Money bonus (ex: +20%)
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Table pour historique des transactions
        Schema::create('money_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('money_package_id')->nullable()->constrained('money_packages');
            $table->string('type');
            $table->integer('amount');
            $table->integer('price')->nullable();
            $table->string('stripe_payment_id')->nullable();
            $table->string('status')->default('completed');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_transactions');
        Schema::dropIfExists('money_packages');
    }
};
