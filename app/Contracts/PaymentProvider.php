<?php

namespace App\Contracts;

use App\Models\PayoutAccount;

interface PaymentProvider
{
    public function createRecipient(PayoutAccount $payoutAccount): string;

    /**
     * @return array{transfer_code: string, status: string}
     */
    public function initiateTransfer(
        string $recipientCode,
        int $amount,
        string $reference,
        string $reason,
    ): array;
}
