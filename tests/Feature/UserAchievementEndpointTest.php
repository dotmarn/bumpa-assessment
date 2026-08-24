<?php

use App\Models\Achievement;
use App\Models\Badge;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;

beforeEach(function () {
    $this->seed([
        AchievementSeeder::class,
        BadgeSeeder::class,
    ]);
});

it('returns the initial progress for a new user', function () {
    $user = User::factory()->create();

    $this->getJson(route('users.achievements', $user))
        ->assertOk()
        ->assertExactJson([
            'unlocked_achievements' => [],
            'next_available_achievements' => ['First Purchase'],
            'current_badge' => 'Beginner',
            'next_badge' => 'Intermediate',
            'remaining_to_unlock_next_badge' => 4,
        ]);
});

it('returns unlocked achievements and only the next available milestone', function () {
    $user = userWithUnlockedAchievements(5);

    $this->getJson(route('users.achievements', $user))
        ->assertOk()
        ->assertExactJson([
            'unlocked_achievements' => [
                'First Purchase',
                '2 Purchases',
                '3 Purchases',
                '4 Purchases',
                '5 Purchases',
            ],
            'next_available_achievements' => ['10 Purchases'],
            'current_badge' => 'Intermediate',
            'next_badge' => 'Advanced',
            'remaining_to_unlock_next_badge' => 3,
        ]);
});

it('does not report an eligible badge as current before it is persisted', function () {
    $user = userWithUnlockedAchievements(4, persistBadges: false);

    $this->getJson(route('users.achievements', $user))
        ->assertOk()
        ->assertJsonPath('current_badge', 'Beginner')
        ->assertJsonPath('next_badge', 'Intermediate')
        ->assertJsonPath('remaining_to_unlock_next_badge', 0);
});

it('reports advanced progress at eight unlocked achievements', function () {
    $user = userWithUnlockedAchievements(8);

    $this->getJson(route('users.achievements', $user))
        ->assertOk()
        ->assertJsonPath('current_badge', 'Advanced')
        ->assertJsonPath('next_badge', 'Master')
        ->assertJsonPath('remaining_to_unlock_next_badge', 2)
        ->assertJsonPath('next_available_achievements.0', '25 Purchases');
});

it('returns completed progress for a master user', function () {
    $user = userWithUnlockedAchievements(10);

    $this->getJson(route('users.achievements', $user))
        ->assertOk()
        ->assertJsonPath('current_badge', 'Master')
        ->assertJsonPath('next_badge', null)
        ->assertJsonPath('remaining_to_unlock_next_badge', 0)
        ->assertJsonPath('next_available_achievements', []);
});

it('returns not found for an unknown user', function () {
    $this->getJson('/users/999999/achievements')->assertNotFound();
});

function userWithUnlockedAchievements(int $count, bool $persistBadges = true): User
{
    $user = User::factory()->create();
    $achievementIds = Achievement::query()
        ->orderBy('threshold')
        ->limit($count)
        ->pluck('id');

    $user->achievements()->attach(
        $achievementIds->mapWithKeys(fn (int $id): array => [
            $id => ['unlocked_at' => now()],
        ]),
    );

    if ($persistBadges) {
        $badgeIds = Badge::query()
            ->where('required_achievements', '>', 0)
            ->where('required_achievements', '<=', $count)
            ->pluck('id');

        $user->badges()->attach(
            $badgeIds->mapWithKeys(fn (int $id): array => [
                $id => ['unlocked_at' => now()],
            ]),
        );
    }

    return $user;
}
