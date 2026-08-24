<?php

namespace App\Services;

use App\Enums\CashbackPaymentStatus;
use App\Models\CashbackPayment;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

class CashbackService
{
    public function handle(User $user, string $badgeName): CashbackPayment
    {
        $cashbackPayment = DB::transaction(function () use ($user, $badgeName): CashbackPayment {
            $userBadge = UserBadge::query()
                ->whereBelongsTo($user)
                ->whereHas('badge', fn ($query) => $query->where('name', $badgeName))
                ->firstOrFail();

            return CashbackPayment::query()->firstOrCreate(
                ['user_badge_id' => $userBadge->getKey()],
                [
                    'amount' => (int) config('services.paystack.cashback_amount'),
                    'currency' => 'NGN',
                    'provider' => 'paystack',
                    'reference' => sprintf('cashback-badge-%010d', $userBadge->getKey()),
                    'status' => CashbackPaymentStatus::Pending,
                ],
            );
        }, attempts: 3);

        return $cashbackPayment;
    }
}
