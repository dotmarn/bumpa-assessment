<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * @return Collection<int, UserBadge>
     */
    public function unlockEligibleBadges(User $user): Collection
    {
        $unlocks = DB::transaction(function () use ($user): Collection {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $achievementCount = $lockedUser->achievementUnlocks()->count();
            $alreadyUnlockedIds = $lockedUser->badgeUnlocks()->pluck('badge_id');

            $eligibleBadges = Badge::query()
                ->where('required_achievements', '>', 0)
                ->where('required_achievements', '<=', $achievementCount)
                ->whereNotIn('id', $alreadyUnlockedIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return $eligibleBadges->map(function (Badge $badge) use ($lockedUser): UserBadge {
                return UserBadge::query()->create([
                    'user_id' => $lockedUser->getKey(),
                    'badge_id' => $badge->getKey(),
                    'unlocked_at' => now(),
                ])->setRelations([
                    'badge' => $badge,
                    'user' => $lockedUser,
                ]);
            });
        }, attempts: 3);

        return $unlocks;
    }
}
