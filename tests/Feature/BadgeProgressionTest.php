<?php

use App\Events\AchievementUnlockedEvent;
use App\Events\BadgeUnlockedEvent;
use App\Listeners\AchievementUnlockedListener;
use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(BadgeSeeder::class);
});

it('registers badge evaluation as an achievement event listener', function () {
    Event::fake();

    Event::assertListening(
        AchievementUnlockedEvent::class,
        AchievementUnlockedListener::class,
    );
});

it('keeps beginner as the default tier without persisting an unlock', function () {
    $user = User::factory()->create();
    Event::fake([BadgeUnlockedEvent::class]);

    $unlocks = app(BadgeService::class)->unlockEligibleBadges($user);

    expect($unlocks)->toBeEmpty()
        ->and($user->badgeUnlocks()->count())->toBe(0);
    Event::assertNotDispatched(BadgeUnlockedEvent::class);
});

it('unlocks intermediate at four achievements with the required event payload', function () {
    $user = userWithAchievementCount(4);
    Event::fake([BadgeUnlockedEvent::class]);

    app(BadgeService::class)->unlockEligibleBadges($user);

    expect($user->badges()->pluck('name')->all())->toBe(['Intermediate']);
    Event::assertDispatchedTimes(BadgeUnlockedEvent::class, 1);
    Event::assertDispatched(
        BadgeUnlockedEvent::class,
        fn (BadgeUnlockedEvent $event): bool => $event->badge_name === 'Intermediate'
            && $event->user->is($user),
    );
});

it('does not unlock the next badge before its threshold', function (int $achievementCount, string $badge) {
    $user = userWithAchievementCount($achievementCount);

    app(BadgeService::class)->unlockEligibleBadges($user);

    expect($user->badges()->where('name', $badge)->exists())->toBeFalse();
})->with([
    'intermediate' => [3, 'Intermediate'],
    'advanced' => [7, 'Advanced'],
    'master' => [9, 'Master'],
]);

it('unlocks advanced and master at their exact thresholds', function (int $achievementCount, array $badges) {
    $user = userWithAchievementCount($achievementCount);

    app(BadgeService::class)->unlockEligibleBadges($user);

    expect($user->badges()->pluck('name')->all())->toEqualCanonicalizing($badges);
})->with([
    'advanced' => [8, ['Intermediate', 'Advanced']],
    'master' => [10, ['Intermediate', 'Advanced', 'Master']],
]);

it('unlocks every crossed badge during delayed processing', function () {
    $user = userWithAchievementCount(10);
    Event::fake([BadgeUnlockedEvent::class]);

    $unlocks = app(BadgeService::class)->unlockEligibleBadges($user);

    expect($unlocks)->toHaveCount(3);
    Event::assertDispatchedTimes(BadgeUnlockedEvent::class, 3);
});

it('is idempotent when achievement events are replayed', function () {
    $user = userWithAchievementCount(4);
    Event::fake([BadgeUnlockedEvent::class]);
    $service = app(BadgeService::class);

    $service->unlockEligibleBadges($user);
    $secondAttempt = $service->unlockEligibleBadges($user);

    expect($secondAttempt)->toBeEmpty()
        ->and($user->badgeUnlocks()->count())->toBe(1);
    Event::assertDispatchedTimes(BadgeUnlockedEvent::class, 1);
});

function userWithAchievementCount(int $count): User
{
    $user = User::factory()->create();

    Achievement::factory()->count($count)->create()->each(
        fn (Achievement $achievement) => UserAchievement::factory()
            ->for($user)
            ->for($achievement)
            ->create(),
    );

    return $user;
}
