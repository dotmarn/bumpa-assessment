<?php

namespace Database\Seeders;

use App\Enums\AchievementCategoriesEnum;
use App\Enums\AchievementMetricEnum;
use App\Models\Achievement;
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
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => '2 Purchases',
                'slug' => '2-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 2,
                'sort_order' => 2,
            ],
            [
                'name' => '3 Purchases',
                'slug' => '3-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 3,
                'sort_order' => 3,
            ],
            [
                'name' => '4 Purchases',
                'slug' => '4-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 4,
                'sort_order' => 4,
            ],
            [
                'name' => '5 Purchases',
                'slug' => '5-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 5,
                'sort_order' => 5,
            ],
            [
                'name' => '10 Purchases',
                'slug' => '10-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 10,
                'sort_order' => 6,
            ],
            [
                'name' => '15 Purchases',
                'slug' => '15-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 15,
                'sort_order' => 7,
            ],
            [
                'name' => '20 Purchases',
                'slug' => '20-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 20,
                'sort_order' => 8,
            ],
            [
                'name' => '25 Purchases',
                'slug' => '25-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 25,
                'sort_order' => 9,
            ],
            [
                'name' => '50 Purchases',
                'slug' => '50-purchases',
                'category' => AchievementCategoriesEnum::Purchases,
                'metric' => AchievementMetricEnum::PurchaseCount,
                'threshold' => 50,
                'sort_order' => 10,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::query()->updateOrCreate(
                ['slug' => $achievement['slug']],
                $achievement,
            );
        }
    }
}
