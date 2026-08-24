<?php

namespace App\Listeners;

use App\Events\BadgeUnlockedEvent;
use App\Services\CashbackService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class BadgeUnlockedListener implements ShouldQueueAfterCommit
{
    public function __construct(private CashbackService $cashbackService) {}

    /**
     * Handle the event.
     */
    public function handle(BadgeUnlockedEvent $event): void
    {
        $this->cashbackService->handle(
            user: $event->user,
            badgeName: $event->badge_name,
        );
    }
}
