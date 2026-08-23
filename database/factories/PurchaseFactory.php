<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
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
            'product_id' => Product::factory(),
            'reference' => (string) Str::ulid(),
            'quantity' => fake()->randomDigitNotZero(),
            'unit_price' => fake()->randomDigitNotZero(),
            'amount' => fake()->numberBetween(
                100_00,
                500_000_00,
            ),
        ];
    }
}
