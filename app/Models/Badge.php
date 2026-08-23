<?php

namespace App\Models;

use Database\Factories\BadgeFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $required_achievements
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(BadgeFactory::class)]
#[Guarded(['id'])]
class Badge extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'required_achievements' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_badges',
        )
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
