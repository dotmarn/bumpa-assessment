<?php

namespace Database\Seeders;

use App\Enums\AchievementCategoriesEnum;
use App\Enums\AchievementMetricEnum;
use App\Models\Achievement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'First Purchase',
                'slug' => 'first-purchase',
                'threshold' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => '5 Purchases',
                'slug' => '5-purchases',
                'threshold' => 5,
                'sort_order' => 2,
            ],
            [
                'name' => '10 Purchases',
                'slug' => '10-purchases',
                'threshold' => 10,
                'sort_order' => 3,
            ],
            [
                'name' => '25 Purchases',
                'slug' => '25-purchases',
                'threshold' => 25,
                'sort_order' => 4,
            ],
            [
                'name' => '50 Purchases',
                'slug' => '50-purchases',
                'threshold' => 50,
                'sort_order' => 5,
            ]
        ];

        foreach ($achievements as $achievement) {
            Achievement::query()->updateOrCreate(
                ['slug' => $achievement['slug']],
                [
                    ...$achievement,
                    'category' => AchievementCategoriesEnum::Purchases,
                    'metric' => AchievementMetricEnum::PurchaseCount,
                ],
            );
        }
    }
}
