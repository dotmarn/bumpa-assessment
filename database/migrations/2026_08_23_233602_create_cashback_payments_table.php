<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cashback_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_badge_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->default(30000);
            $table->char('currency', 3)->default('NGN');
            $table->string('provider')->default('paystack');
            $table->string('reference', 50)->unique();
            $table->string('status')->default('pending')->index();
            $table->string('recipient_code')->nullable();
            $table->string('provider_transfer_code')->nullable()->unique();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashback_payments');
    }
};
