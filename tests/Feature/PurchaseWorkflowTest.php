<?php

use App\Events\PurchaseCreatedEvent;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('validates the purchase payload and returns validation errors', function (array $payload, array $errors) {
    $user = User::factory()->create();

    $this->postJson(route('users.purchase', $user), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing values' => [[], ['reference', 'product_id', 'quantity']],
    'invalid values' => [[
        'reference' => 'not-a-ulid',
        'product_id' => 999_999,
        'quantity' => 0,
    ], ['reference', 'product_id', 'quantity']],
]);

it('rejects inactive products when creating a purchase', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['is_active' => false]);

    $this->postJson(route('users.purchase', $user), [
        'reference' => (string) Str::ulid(),
        'product_id' => $product->getKey(),
        'quantity' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('product_id');
});

it('creates a purchase successfully using the product price', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 12_500]);
    $reference = (string) Str::ulid();
    Event::fake([PurchaseCreatedEvent::class]);

    $response = $this->postJson(route('users.purchase', $user), [
        'reference' => $reference,
        'product_id' => $product->getKey(),
        'quantity' => 3,
        'amount' => 1,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.reference', $reference)
        ->assertJsonPath('data.product.id', $product->getKey())
        ->assertJsonPath('data.quantity', 3)
        ->assertJsonPath('data.unit_price', 12_500)
        ->assertJsonPath('data.amount', 37_500);

    $purchase = Purchase::query()->sole();

    expect($purchase)
        ->user_id->toBe($user->getKey())
        ->product_id->toBe($product->getKey())
        ->unit_price->toBe(12_500)
        ->amount->toBe(37_500);

    Event::assertDispatched(
        PurchaseCreatedEvent::class,
        fn (PurchaseCreatedEvent $event): bool => $event->purchase->is($purchase),
    );
});

it('returns the original purchase for an identical replay without dispatching twice', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 2_000]);
    $payload = [
        'reference' => (string) Str::ulid(),
        'product_id' => $product->getKey(),
        'quantity' => 2,
    ];
    Event::fake([PurchaseCreatedEvent::class]);

    $this->postJson(route('users.purchase', $user), $payload)->assertCreated();
    $this->postJson(route('users.purchase', $user), $payload)
        ->assertOk()
        ->assertJsonPath('data.reference', $payload['reference']);

    expect(Purchase::query()->count())->toBe(1);
    Event::assertDispatchedTimes(PurchaseCreatedEvent::class, 1);
});

it('rejects reuse of a reference with different purchase details', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $reference = (string) Str::ulid();

    Purchase::factory()->for($user)->for($product)->create([
        'reference' => $reference,
        'quantity' => 1,
    ]);

    $this->postJson(route('users.purchase', $user), [
        'reference' => $reference,
        'product_id' => $product->getKey(),
        'quantity' => 2,
    ])->assertConflict();

    expect(Purchase::query()->count())->toBe(1);
});
