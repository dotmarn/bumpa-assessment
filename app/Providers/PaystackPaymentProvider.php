<?php

namespace App\Providers;

use App\Contracts\PaymentProvider;
use App\Models\PayoutAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class PaystackPaymentProvider implements PaymentProvider
{
    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function createRecipient(PayoutAccount $payoutAccount): string
    {
        $response = $this->client()
            ->post('/transferrecipient', [
                'type' => 'nuban',
                'name' => $payoutAccount->account_name,
                'account_number' => $payoutAccount->account_number,
                'bank_code' => $payoutAccount->bank_code,
                'currency' => 'NGN',
            ])
            ->throw();

        $recipientCode = $response->json('data.recipient_code');

        if (! is_string($recipientCode) || $recipientCode === '') {
            throw new UnexpectedValueException('Paystack did not return a recipient code.');
        }

        return $recipientCode;
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function initiateTransfer(
        string $recipientCode,
        int $amount,
        string $reference,
        string $reason,
    ): array {
        $response = $this->client()
            ->post('/transfer', [
                'source' => 'balance',
                'amount' => $amount,
                'recipient' => $recipientCode,
                'reference' => $reference,
                'reason' => $reason,
                'currency' => 'NGN',
            ])
            ->throw();

        $transferCode = $response->json('data.transfer_code');
        $status = $response->json('data.status');

        if (! is_string($transferCode) || ! is_string($status)) {
            throw new UnexpectedValueException('Paystack returned an invalid transfer response.');
        }

        return [
            'transfer_code' => $transferCode,
            'status' => $status,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paystack.base_url'))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.paystack.secret_key'))
            ->connectTimeout(3)
            ->timeout(10);
    }
}
