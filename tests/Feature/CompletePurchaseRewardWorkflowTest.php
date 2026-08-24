<?php

use App\Enums\CashbackPaymentStatus;
use App\Models\CashbackPayment;
use App\Models\PayoutAccount;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('processes a purchase through achievement badge and cashback completion', function () {
    $this->seed([AchievementSeeder::class, BadgeSeeder::class]);

    config()->set('services.paystack.secret_key', 'sk_test_example');
    config()->set('services.paystack.base_url', 'https://api.paystack.co');
    config()->set('services.paystack.cashback_amount', 30000);

    $user = User::factory()->create();
    $product = Product::factory()->create();
    PayoutAccount::factory()->for($user)->create([
        'account_number' => '0000000000',
        'account_name' => 'Test Customer',
        'bank_code' => '057',
        'recipient_code' => null,
    ]);

    Http::preventStrayRequests();
    Http::fakeSequence()
        ->push([
            'status' => true,
            'data' => ['recipient_code' => 'RCP_complete_workflow'],
        ])
        ->push([
            'status' => true,
            'data' => [
                'transfer_code' => 'TRF_complete_workflow',
                'status' => 'success',
            ],
        ]);

    foreach (range(1, 4) as $_) {
        $this->postJson(route('users.purchase', $user), [
            'reference' => (string) Str::ulid(),
            'product_id' => $product->getKey(),
            'quantity' => 1,
        ])->assertCreated();
    }

    expect($user->purchases()->count())->toBe(4)
        ->and($user->achievements()->pluck('name')->all())->toBe([
            'First Purchase',
            '2 Purchases',
            '3 Purchases',
            '4 Purchases',
        ])
        ->and($user->badges()->pluck('name')->all())->toBe(['Intermediate']);

    $cashbackPayment = CashbackPayment::query()->sole();

    expect($cashbackPayment)
        ->amount->toBe(30000)
        ->currency->toBe('NGN')
        ->status->toBe(CashbackPaymentStatus::Succeeded)
        ->recipient_code->toBe('RCP_complete_workflow')
        ->provider_transfer_code->toBe('TRF_complete_workflow')
        ->processed_at->not->toBeNull();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paystack.co/transfer'
        && $request['amount'] === 30000
        && $request['recipient'] === 'RCP_complete_workflow'
        && $request['reason'] === 'Cashback for unlocking the Intermediate badge');
});
