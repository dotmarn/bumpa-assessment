<?php

use App\Enums\CashbackPaymentStatus;
use App\Events\BadgeUnlockedEvent;
use App\Jobs\ProcessBadgeCashback;
use App\Listeners\BadgeUnlockedListener;
use App\Models\Badge;
use App\Models\CashbackPayment;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\CashbackService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_test_example');
    config()->set('services.paystack.base_url', 'https://api.paystack.co');
    config()->set('services.paystack.cashback_amount', 30000);
    Http::preventStrayRequests();
});

it('registers cashback creation as a badge event listener', function () {
    Event::fake();

    Event::assertListening(BadgeUnlockedEvent::class, BadgeUnlockedListener::class);
});

it('creates one cashback ledger entry for a badge and queues its processing', function () {
    Queue::fake([ProcessBadgeCashback::class]);
    [$user, $userBadge] = cashbackUserBadge();
    $service = app(CashbackService::class);

    $cashbackPayment = $service->handle($user, $userBadge->badge->name);
    $service->handle($user, $userBadge->badge->name);

    expect(CashbackPayment::query()->count())->toBe(1)
        ->and($cashbackPayment->amount)->toBe(30000)
        ->and($cashbackPayment->currency)->toBe('NGN')
        ->and($cashbackPayment->reference)->toBe(
            sprintf('cashback-payout-%010d', $userBadge->getKey()),
        );

    Queue::assertPushedTimes(ProcessBadgeCashback::class, 1);
});

it('creates a recipient and transfers three hundred naira', function () {
    [$user, $userBadge] = cashbackUserBadge();
    $payoutAccount = PayoutAccount::factory()->for($user)->create([
        'account_number' => '0123456789',
        'account_name' => 'Test Customer',
        'recipient_code' => null,
    ]);
    $cashbackPayment = CashbackPayment::factory()->for($userBadge)->create();
    Http::fakeSequence()
        ->push([
            'status' => true,
            'data' => ['recipient_code' => 'RCP_customer'],
        ])
        ->push([
            'status' => true,
            'data' => [
                'transfer_code' => 'TRF_cashback',
                'status' => 'success',
            ],
        ]);

    runCashbackJob($cashbackPayment);

    expect($cashbackPayment->refresh())
        ->status->toBe(CashbackPaymentStatus::Succeeded)
        ->recipient_code->toBe('RCP_customer')
        ->provider_transfer_code->toBe('TRF_cashback')
        ->processed_at->not->toBeNull()
        ->and($payoutAccount->refresh()->recipient_code)->toBe('RCP_customer')
        ->and($payoutAccount->getRawOriginal('account_number'))->not->toBe('0123456789');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.paystack.co/transferrecipient'
            && $request['type'] === 'nuban'
            && $request['account_number'] === '0123456789';
    });
    Http::assertSent(function (Request $request) use ($cashbackPayment): bool {
        return $request->url() === 'https://api.paystack.co/transfer'
            && $request['amount'] === 30000
            && $request['recipient'] === 'RCP_customer'
            && $request['reference'] === $cashbackPayment->reference;
    });
});

it('reuses an existing recipient code', function () {
    [$user, $userBadge] = cashbackUserBadge();
    PayoutAccount::factory()->for($user)->create(['recipient_code' => 'RCP_existing']);
    $cashbackPayment = CashbackPayment::factory()->for($userBadge)->create();
    Http::fake([
        'api.paystack.co/transfer' => Http::response([
            'status' => true,
            'data' => [
                'transfer_code' => 'TRF_existing',
                'status' => 'pending',
            ],
        ]),
    ]);

    runCashbackJob($cashbackPayment);

    expect($cashbackPayment->refresh()->status)->toBe(CashbackPaymentStatus::Submitted);
    Http::assertSentCount(1);
});

it('records a recoverable failure when the payout account is missing', function () {
    [, $userBadge] = cashbackUserBadge();
    $cashbackPayment = CashbackPayment::factory()->for($userBadge)->create();

    runCashbackJob($cashbackPayment);

    expect($cashbackPayment->refresh())
        ->status->toBe(CashbackPaymentStatus::Failed)
        ->failure_reason->toContain('payout account');
    Http::assertNothingSent();
});

it('records a definitive provider rejection without retrying', function () {
    [$user, $userBadge] = cashbackUserBadge();
    PayoutAccount::factory()->for($user)->create(['recipient_code' => 'RCP_invalid']);
    $cashbackPayment = CashbackPayment::factory()->for($userBadge)->create();
    Http::fake([
        'api.paystack.co/transfer' => Http::response([
            'status' => false,
            'message' => 'Invalid recipient',
        ], 422),
    ]);

    runCashbackJob($cashbackPayment);

    expect($cashbackPayment->refresh())
        ->status->toBe(CashbackPaymentStatus::Failed)
        ->failure_reason->toBe('Invalid recipient');
    Http::assertSentCount(1);
});

it('allows connection failures to be retried by the queue', function () {
    [$user, $userBadge] = cashbackUserBadge();
    PayoutAccount::factory()->for($user)->create(['recipient_code' => 'RCP_retry']);
    $cashbackPayment = CashbackPayment::factory()->for($userBadge)->create();
    Http::fake([
        'api.paystack.co/transfer' => Http::failedConnection(),
    ]);

    expect(fn () => runCashbackJob($cashbackPayment))
        ->toThrow(ConnectionException::class)
        ->and($cashbackPayment->refresh()->status)->toBe(CashbackPaymentStatus::Processing);

});

it('does not issue another transfer after successful processing', function () {
    [$user, $userBadge] = cashbackUserBadge();
    PayoutAccount::factory()->for($user)->create(['recipient_code' => 'RCP_once']);
    $cashbackPayment = CashbackPayment::factory()->for($userBadge)->create();
    Http::fake([
        'api.paystack.co/transfer' => Http::response([
            'status' => true,
            'data' => [
                'transfer_code' => 'TRF_once',
                'status' => 'success',
            ],
        ]),
    ]);

    runCashbackJob($cashbackPayment);
    runCashbackJob($cashbackPayment);

    Http::assertSentCount(1);
    expect($cashbackPayment->refresh()->status)->toBe(CashbackPaymentStatus::Succeeded);
});

/**
 * @return array{User, UserBadge}
 */
function cashbackUserBadge(): array
{
    $user = User::factory()->create();
    $badge = Badge::factory()->create();
    $userBadge = UserBadge::factory()->for($user)->for($badge)->create();

    return [$user, $userBadge->setRelation('badge', $badge)];
}

function runCashbackJob(CashbackPayment $cashbackPayment): void
{
    app()->call([new ProcessBadgeCashback($cashbackPayment->getKey()), 'handle']);
}
