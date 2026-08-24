<?php

namespace Database\Factories;

use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutAccount>
 */
class PayoutAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_code' => '044',
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'recipient_code' => null,
        ];
    }
}
