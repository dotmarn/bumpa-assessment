<?php

namespace App\Services;

use App\Events\PurchaseCreatedEvent;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PurchaseService
{
    /**
     * @param  array{reference: string, product_id: int, quantity: int}  $payload
     * @return array{purchase: Purchase, was_created: bool}
     */
    public function create(User $user, array $payload): array
    {
        $result = DB::transaction(function () use ($user, $payload): array {
            $existingPurchase = Purchase::query()
                ->where('reference', $payload['reference'])
                ->lockForUpdate()
                ->first();

            if ($existingPurchase !== null) {
                $this->ensureIdempotentReplay($existingPurchase, $user, $payload);

                return [
                    'purchase' => $existingPurchase,
                    'was_created' => false,
                ];
            }

            $product = Product::query()->findOrFail($payload['product_id']);

            if (! $product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => ['The selected product is inactive.'],
                ]);
            }

            $purchase = Purchase::query()->create([
                'user_id' => $user->getKey(),
                'product_id' => $product->getKey(),
                'reference' => $payload['reference'],
                'quantity' => $payload['quantity'],
                'unit_price' => $product->price,
                'amount' => $product->price * $payload['quantity'],
            ]);

            return [
                'purchase' => $purchase,
                'was_created' => true,
            ];
        }, attempts: 3);

        if ($result['was_created']) {
            PurchaseCreatedEvent::dispatch($result['purchase']);
        }

        return $result;
    }

    /**
     * @param  array{reference: string, product_id: int, quantity: int}  $payload
     */
    private function ensureIdempotentReplay(Purchase $purchase, User $user, array $payload): void
    {
        $matchesOriginalRequest = $purchase->user_id === $user->getKey()
            && $purchase->product_id === $payload['product_id']
            && $purchase->quantity === $payload['quantity'];

        if ($matchesOriginalRequest) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'The purchase reference has already been used with different details.',
        ], Response::HTTP_CONFLICT));
    }
}
