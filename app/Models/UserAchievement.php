<?php

namespace App\Models;

use Database\Factories\UserAchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $achievement_id
 * @property Carbon $unlocked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(UserAchievementFactory::class)]
#[Guarded(['id'])]
class UserAchievement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
