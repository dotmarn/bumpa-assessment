<?php

namespace App\Models;

use App\Enums\AchievementCategoriesEnum;
use App\Enums\AchievementMetricEnum;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property AchievementCategoriesEnum $category
 * @property AchievementMetricEnum $metric
 * @property int $threshold
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(AchievementFactory::class)]
#[Guarded(['id'])]
class Achievement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => AchievementCategoriesEnum::class,
            'metric' => AchievementMetricEnum::class,
        ];
    }
}
