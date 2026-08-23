<?php

namespace Database\Factories;

use App\Enums\AchievementCategoriesEnum;
use App\Enums\AchievementMetricEnum;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'category' => AchievementCategoriesEnum::Purchases,
            'metric' => AchievementMetricEnum::PurchaseCount,
            'threshold' => fake()->numberBetween(1, 1000),
            'sort_order' => 1,
        ];
    }

    public function forPurchaseCount(
        int $threshold,
        string $name,
        int $sortOrder = 1,
    ): static {
        return $this->state(fn (): array => [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => AchievementCategoriesEnum::Purchases,
            'metric' => AchievementMetricEnum::PurchaseCount,
            'threshold' => $threshold,
            'sort_order' => $sortOrder,
        ]);
    }
}
