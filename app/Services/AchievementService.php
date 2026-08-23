<?php

namespace App\Services;

use App\Enums\AchievementMetricEnum;
use App\Events\AchievementUnlockedEvent;
use App\Models\Achievement;
use App\Models\Purchase;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    /**
     * @return Collection<int, UserAchievement>
     */
    public function unlockForPurchase(Purchase $purchase): Collection
    {
        $unlocks = DB::transaction(function () use ($purchase): Collection {
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($purchase->user_id);

            $purchaseCount = $user->purchases()->count();

            $alreadyUnlockedIds = $user->achievementUnlocks()
                ->pluck('achievement_id');

            $eligibleAchievements = Achievement::query()
                ->whereNotIn('id', $alreadyUnlockedIds)
                ->where('metric', AchievementMetricEnum::PurchaseCount)
                ->where('threshold', '<=', $purchaseCount)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return $eligibleAchievements->map(function (Achievement $achievement) use ($user): UserAchievement {
                return UserAchievement::query()->create([
                    'user_id' => $user->getKey(),
                    'achievement_id' => $achievement->getKey(),
                    'unlocked_at' => now(),
                ])->setRelations([
                    'achievement' => $achievement,
                    'user' => $user,
                ]);
            });
        }, attempts: 3);

        $unlocks->each(function (UserAchievement $unlock): void {
            AchievementUnlockedEvent::dispatch(
                achievement_name: $unlock->achievement->name,
                user: $unlock->user,
            );
        });

        return $unlocks;
    }
}
