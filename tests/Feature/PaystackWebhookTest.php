<?php

use App\Contracts\PaymentProvider;
use App\Enums\CashbackPaymentStatus;
use App\Jobs\ProcessBadgeCashback;
use App\Models\Badge;
use App\Models\CashbackPayment;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_test_webhook');
});

it('rejects webhook requests with an invalid signature', function () {
    $cashbackPayment = webhookCashbackPayment();

    sendPaystackWebhook($this, webhookPayload('transfer.success', $cashbackPayment), 'invalid')
        ->assertForbidden();

    expect($cashbackPayment->refresh()->status)->toBe(CashbackPaymentStatus::Submitted);
});

it('marks a submitted transfer as succeeded and handles duplicate delivery', function () {
    $cashbackPayment = webhookCashbackPayment();
    $payload = webhookPayload('transfer.success', $cashbackPayment);

    sendPaystackWebhook($this, $payload)->assertOk()->assertJson(['status' => true]);
    $processedAt = $cashbackPayment->refresh()->processed_at;
    sendPaystackWebhook($this, $payload)->assertOk();

    expect($cashbackPayment->refresh())
        ->status->toBe(CashbackPaymentStatus::Succeeded)
        ->provider_transfer_code->toBe('TRF_webhook')
        ->failure_reason->toBeNull()
        ->processed_at->toEqual($processedAt);
});

it('marks failed and reversed transfers as failed', function (string $event, string $reason) {
    $cashbackPayment = webhookCashbackPayment();
    $payload = webhookPayload($event, $cashbackPayment, [
        'failure_reason' => $reason,
    ]);

    sendPaystackWebhook($this, $payload)->assertOk();

    expect($cashbackPayment->refresh())
        ->status->toBe(CashbackPaymentStatus::Failed)
        ->failure_reason->toBe($reason)
        ->processed_at->not->toBeNull();
})->with([
    'failed' => ['transfer.failed', 'The destination account rejected the transfer.'],
    'reversed' => ['transfer.reversed', 'The transfer was reversed.'],
]);

it('does not let an older failed event downgrade a successful transfer', function () {
    $cashbackPayment = webhookCashbackPayment(CashbackPaymentStatus::Succeeded);

    sendPaystackWebhook($this, webhookPayload('transfer.failed', $cashbackPayment, [
        'failure_reason' => 'Delayed failure event',
    ]))->assertOk();

    expect($cashbackPayment->refresh())
        ->status->toBe(CashbackPaymentStatus::Succeeded)
        ->failure_reason->toBeNull();
});

it('acknowledges unrelated events and unknown references without changing payments', function () {
    $cashbackPayment = webhookCashbackPayment();

    sendPaystackWebhook($this, webhookPayload('charge.success', $cashbackPayment))->assertOk();
    sendPaystackWebhook($this, [
        'event' => 'transfer.success',
        'data' => [
            'reference' => 'unknown-reference',
            'transfer_code' => 'TRF_unknown',
        ],
    ])->assertOk();

    expect($cashbackPayment->refresh()->status)->toBe(CashbackPaymentStatus::Submitted);
});

it('keeps a webhook success when it arrives before the transfer job updates its response', function () {
    $cashbackPayment = webhookCashbackPayment(CashbackPaymentStatus::Pending);
    $user = $cashbackPayment->userBadge->user;
    PayoutAccount::factory()->for($user)->create(['recipient_code' => 'RCP_webhook']);

    $this->mock(PaymentProvider::class, function (MockInterface $mock) use ($cashbackPayment): void {
        $mock->shouldReceive('initiateTransfer')
            ->once()
            ->andReturnUsing(function () use ($cashbackPayment): array {
                sendPaystackWebhook($this, webhookPayload('transfer.success', $cashbackPayment))
                    ->assertOk();

                return [
                    'transfer_code' => 'TRF_webhook',
                    'status' => 'pending',
                ];
            });
    });

    app()->call([new ProcessBadgeCashback($cashbackPayment->getKey()), 'handle']);

    expect($cashbackPayment->refresh()->status)->toBe(CashbackPaymentStatus::Succeeded);
});

function webhookCashbackPayment(
    CashbackPaymentStatus $status = CashbackPaymentStatus::Submitted,
): CashbackPayment {
    $user = User::factory()->create();
    $badge = Badge::factory()->create();
    $userBadge = UserBadge::factory()->for($user)->for($badge)->create();

    return CashbackPayment::factory()
        ->for($userBadge)
        ->create([
            'status' => $status,
            'provider_transfer_code' => $status === CashbackPaymentStatus::Pending
                ? null
                : 'TRF_webhook',
        ])
        ->setRelation('userBadge', $userBadge->setRelations([
            'user' => $user,
            'badge' => $badge,
        ]));
}

/**
 * @param  array<string, mixed>  $extraData
 * @return array{event: string, data: array<string, mixed>}
 */
function webhookPayload(
    string $event,
    CashbackPayment $cashbackPayment,
    array $extraData = [],
): array {
    return [
        'event' => $event,
        'data' => [
            'reference' => $cashbackPayment->reference,
            'transfer_code' => 'TRF_webhook',
            ...$extraData,
        ],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function sendPaystackWebhook(
    TestCase $testCase,
    array $payload,
    ?string $signature = null,
): TestResponse {
    $content = json_encode($payload, JSON_THROW_ON_ERROR);

    return $testCase->call(
        'POST',
        route('paystack.webhook'),
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature
                ?? hash_hmac('sha512', $content, (string) config('services.paystack.secret_key')),
        ],
        content: $content,
    );
}
