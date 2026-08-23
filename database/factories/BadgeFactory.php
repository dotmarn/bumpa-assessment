<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'required_achievements' => fake()->unique()->numberBetween(1, 100),
            'sort_order' => 1,
        ];
    }

    public function requiring(
        int $achievementCount,
        string $name,
        int $sortOrder = 1,
    ): static {
        return $this->state(fn (): array => [
            'name' => $name,
            'slug' => Str::slug($name),
            'required_achievements' => $achievementCount,
            'sort_order' => $sortOrder,
        ]);
    }
}
