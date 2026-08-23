<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AchievementProgressService;
use Illuminate\Http\JsonResponse;

class UserAchievementController extends Controller
{
    public function __construct(private readonly AchievementProgressService $achievementProgressService) {}

    public function __invoke(User $user): JsonResponse
    {
        return response()->json(
            $this->achievementProgressService->forUser($user),
        );
    }
}
