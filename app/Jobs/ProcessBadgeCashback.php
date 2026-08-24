<?php

namespace App\Jobs;

use App\Contracts\PaymentProvider;
use App\Enums\CashbackPaymentStatus;
use App\Models\CashbackPayment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;

class ProcessBadgeCashback implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $cashbackPaymentId) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentProvider $paymentProvider): void
    {
        $cashbackPayment = CashbackPayment::query()
            ->with(['userBadge.badge', 'userBadge.user.payoutAccount'])
            ->findOrFail($this->cashbackPaymentId);

        if (in_array($cashbackPayment->status, [
            CashbackPaymentStatus::Submitted,
            CashbackPaymentStatus::Succeeded,
        ], true)) {
            return;
        }

        $payoutAccount = $cashbackPayment->userBadge->user->payoutAccount;

        if ($payoutAccount === null) {
            $this->markAsFailed($cashbackPayment, 'The user does not have a payout account.');

            return;
        }

        $cashbackPayment->update([
            'status' => CashbackPaymentStatus::Processing,
            'failure_reason' => null,
        ]);

        try {
            $recipientCode = $payoutAccount->recipient_code;

            if ($recipientCode === null) {
                $recipientCode = $paymentProvider->createRecipient($payoutAccount);
                $payoutAccount->update(['recipient_code' => $recipientCode]);
            }

            $result = $paymentProvider->initiateTransfer(
                recipientCode: $recipientCode,
                amount: $cashbackPayment->amount,
                reference: $cashbackPayment->reference,
                reason: sprintf('Cashback for unlocking the %s badge', $cashbackPayment->userBadge->badge->name),
            );

            CashbackPayment::query()
                ->whereKey($cashbackPayment->getKey())
                ->where('status', CashbackPaymentStatus::Processing)
                ->update([
                    'status' => $result['status'] === 'success'
                        ? CashbackPaymentStatus::Succeeded
                        : CashbackPaymentStatus::Submitted,
                    'recipient_code' => $recipientCode,
                    'provider_transfer_code' => $result['transfer_code'],
                    'processed_at' => now(),
                ]);
        } catch (RequestException $exception) {
            if ($exception->response->serverError() || $exception->response->status() === 429) {
                throw $exception;
            }

            $this->markAsFailed(
                $cashbackPayment,
                $exception->response->json('message', 'Paystack rejected the cashback transfer.'),
            );
        } catch (UnexpectedValueException $exception) {
            $this->markAsFailed($cashbackPayment, $exception->getMessage());
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->cashbackPaymentId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function failed(?Throwable $exception): void
    {
        $cashbackPayment = CashbackPayment::query()->find($this->cashbackPaymentId);

        if ($cashbackPayment !== null && ! in_array($cashbackPayment->status, [
            CashbackPaymentStatus::Submitted,
            CashbackPaymentStatus::Succeeded,
        ], true)) {
            $this->markAsFailed(
                $cashbackPayment,
                'Cashback processing exhausted all retry attempts.',
            );
        }
    }

    private function markAsFailed(CashbackPayment $cashbackPayment, string $reason): void
    {
        $cashbackPayment->update([
            'status' => CashbackPaymentStatus::Failed,
            'failure_reason' => Str::limit($reason, 1000),
        ]);
    }
}
