<?php

use App\Events\AchievementUnlockedEvent;
use App\Events\PurchaseCreatedEvent;
use App\Listeners\UnlockPurchaseAchievementsListener;
use App\Models\Achievement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AchievementService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(AchievementSeeder::class);
});

it('registers purchase achievement evaluation as an event listener', function () {
    Event::fake();

    Event::assertListening(
        PurchaseCreatedEvent::class,
        UnlockPurchaseAchievementsListener::class,
    );
});

it('unlocks the first purchase milestone', function () {
    $user = User::factory()->create();
    $purchase = Purchase::factory()->for($user)->create();
    Event::fake([AchievementUnlockedEvent::class]);

    app(AchievementService::class)->unlockForPurchase($purchase);

    expect($user->achievements()->pluck('name')->all())->toBe(['First Purchase']);

    Event::assertDispatchedTimes(AchievementUnlockedEvent::class, 1);
    Event::assertDispatched(
        AchievementUnlockedEvent::class,
        fn (AchievementUnlockedEvent $event): bool => $event->achievement_name === 'First Purchase'
            && $event->user->is($user),
    );
});

it('counts every purchase even when the same product is purchased repeatedly', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    Purchase::factory()->count(4)->for($user)->for($product)->create();
    $purchase = Purchase::factory()->for($user)->for($product)->create();

    app(AchievementService::class)->unlockForPurchase($purchase);

    expect($user->achievements()->pluck('name')->all())->toEqualCanonicalizing([
        'First Purchase',
        '2 Purchases',
        '3 Purchases',
        '4 Purchases',
        '5 Purchases',
    ]);
});

it('unlocks every crossed milestone when progress is evaluated', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    Purchase::factory()->count(50)->for($user)->for($product)->create();
    Event::fake([AchievementUnlockedEvent::class]);

    $unlocks = app(AchievementService::class)
        ->unlockForPurchase($user->purchases()->latest('id')->firstOrFail());

    expect($unlocks)->toHaveCount(10);
    Event::assertDispatchedTimes(AchievementUnlockedEvent::class, 10);
});

it('is idempotent when the same purchase event is processed again', function () {
    $user = User::factory()->create();
    $purchase = Purchase::factory()->for($user)->create();
    Event::fake([AchievementUnlockedEvent::class]);
    $service = app(AchievementService::class);

    $service->unlockForPurchase($purchase);
    $secondAttempt = $service->unlockForPurchase($purchase);

    expect($secondAttempt)->toBeEmpty()
        ->and($user->achievementUnlocks()->count())->toBe(1);
    Event::assertDispatchedTimes(AchievementUnlockedEvent::class, 1);
});
