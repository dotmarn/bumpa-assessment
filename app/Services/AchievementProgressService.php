<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Badge;
use App\Models\User;

class AchievementProgressService
{
    /**
     * @return array{
     *     unlocked_achievements: list<string>,
     *     next_available_achievements: list<string>,
     *     current_badge: string|null,
     *     next_badge: string|null,
     *     remaining_to_unlock_next_badge: int
     * }
     */
    public function forUser(User $user): array
    {
        $unlockedAchievements = $user->achievements()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('achievements.id')
            ->get(['achievements.id', 'name', 'category']);

        $nextAvailableAchievements = Achievement::query()
            ->whereNotIn('id', $unlockedAchievements->modelKeys())
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'category'])
            ->groupBy(fn (Achievement $achievement): string => $achievement->category->value)
            ->map(fn ($achievements): string => $achievements->first()->name)
            ->values()
            ->all();

        $achievementCount = $unlockedAchievements->count();
        $badges = Badge::query()
            ->orderBy('required_achievements')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'required_achievements']);

        $earnedBadge = $user->badges()
            ->orderByDesc('required_achievements')
            ->orderByDesc('sort_order')
            ->first(['badges.id', 'name', 'required_achievements']);
        $currentBadge = $earnedBadge ?? $badges
            ->first(fn (Badge $badge): bool => $badge->required_achievements === 0);
        $nextBadge = $badges
            ->first(fn (Badge $badge): bool => $badge->required_achievements > ($currentBadge?->required_achievements ?? 0));

        return [
            'unlocked_achievements' => $unlockedAchievements->pluck('name')->all(),
            'next_available_achievements' => $nextAvailableAchievements,
            'current_badge' => $currentBadge?->name,
            'next_badge' => $nextBadge?->name,
            'remaining_to_unlock_next_badge' => $nextBadge === null
                ? 0
                : max(0, $nextBadge->required_achievements - $achievementCount),
        ];
    }
}
