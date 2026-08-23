<?php

namespace App\Listeners;

use App\Events\AchievementUnlockedEvent;
use App\Services\BadgeService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class AchievementUnlockedListener implements ShouldQueueAfterCommit
{
    public function __construct(private BadgeService $badgeService) {}

    /**
     * Handle the event.
     */
    public function handle(AchievementUnlockedEvent $event): void
    {
        $this->badgeService->unlockEligibleBadges($event->user);
    }
}
