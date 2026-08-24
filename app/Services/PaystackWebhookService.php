<?php

namespace App\Services;

use App\Enums\CashbackPaymentStatus;
use App\Models\CashbackPayment;

class PaystackWebhookService
{
    /**
     * @param  array{event: string, data: array<string, mixed>}  $payload
     */
    public function handle(array $payload): void
    {
        if (! in_array($payload['event'], [
            'transfer.success',
            'transfer.failed',
            'transfer.reversed',
        ], true)) {
            return;
        }

        $reference = data_get($payload, 'data.reference');

        if (! is_string($reference) || $reference === '') {
            return;
        }

        $cashbackPayment = CashbackPayment::query()
            ->where('reference', $reference)
            ->first();

        if ($cashbackPayment === null) {
            return;
        }

        if ($payload['event'] === 'transfer.failed'
            && $cashbackPayment->status === CashbackPaymentStatus::Succeeded) {
            return;
        }

        $status = $payload['event'] === 'transfer.success'
            ? CashbackPaymentStatus::Succeeded
            : CashbackPaymentStatus::Failed;
        $transferCode = data_get($payload, 'data.transfer_code');

        $cashbackPayment->update([
            'status' => $status,
            'provider_transfer_code' => is_string($transferCode) && $transferCode !== ''
                ? $transferCode
                : $cashbackPayment->provider_transfer_code,
            'failure_reason' => $status === CashbackPaymentStatus::Succeeded
                ? null
                : $this->failureReason($payload),
            'processed_at' => $cashbackPayment->processed_at ?? now(),
        ]);
    }

    /**
     * @param  array{event: string, data: array<string, mixed>}  $payload
     */
    private function failureReason(array $payload): string
    {
        $reason = data_get($payload, 'data.failure_reason')
            ?? data_get($payload, 'data.message');

        return is_string($reason) && $reason !== ''
            ? $reason
            : sprintf('Paystack reported %s.', $payload['event']);
    }
}
