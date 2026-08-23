<?php

namespace App\Listeners;

use App\Events\PurchaseCreatedEvent;
use App\Services\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

class UnlockPurchaseAchievementsListener implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function __construct(private AchievementService $achievementService) {}

    /**
     * Handle the event.
     */
    public function handle(PurchaseCreatedEvent $event): void
    {
        $this->achievementService->unlockForPurchase($event->purchase);
    }
}
