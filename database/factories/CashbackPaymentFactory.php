<?php

namespace Database\Factories;

use App\Enums\CashbackPaymentStatus;
use App\Models\CashbackPayment;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashbackPayment>
 */
class CashbackPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_badge_id' => UserBadge::factory(),
            'amount' => 30000,
            'currency' => 'NGN',
            'provider' => 'paystack',
            'reference' => 'cashback-'.fake()->unique()->uuid(),
            'status' => CashbackPaymentStatus::Pending,
        ];
    }
}
