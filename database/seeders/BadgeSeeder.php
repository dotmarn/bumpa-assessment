<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badges = [
            [
                'name' => 'Beginner',
                'slug' => 'beginner',
                'required_achievements' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => 'Intermediate',
                'slug' => 'intermediate',
                'required_achievements' => 4,
                'sort_order' => 2,
            ],
            [
                'name' => 'Advanced',
                'slug' => 'advanced',
                'required_achievements' => 8,
                'sort_order' => 3,
            ],
            [
                'name' => 'Master',
                'slug' => 'master',
                'required_achievements' => 10,
                'sort_order' => 4,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::query()->updateOrCreate(
                ['slug' => $badge['slug']],
                $badge,
            );
        }

    }
}
