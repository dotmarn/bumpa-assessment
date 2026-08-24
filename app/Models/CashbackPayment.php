<?php

namespace App\Models;

use App\Enums\CashbackPaymentStatus;
use Database\Factories\CashbackPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(CashbackPaymentFactory::class)]
#[Guarded(['id'])]
class CashbackPayment extends Model
{
    use HasFactory;

    protected $attributes = [
        'amount' => 30000,
        'currency' => 'NGN',
        'provider' => 'paystack',
        'status' => CashbackPaymentStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => CashbackPaymentStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function userBadge(): BelongsTo
    {
        return $this->belongsTo(UserBadge::class);
    }
}
