<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function handle(
        Invoice $invoice,
        string $amount,
        string $paidAt,
        ?string $reference = null,
        ?string $submissionId = null,
    ): Payment {
        if (
            ! is_numeric($amount)
            || ! preg_match('/\A[0-9]{1,12}(?:\.[0-9]{1,2})?\z/', $amount)
            || bccomp($amount, '0.00', 2) <= 0
        ) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a positive amount with up to two decimal places.',
            ]);
        }

        $submissionId = $submissionId === null ? null : strtolower($submissionId);

        return DB::transaction(function () use ($invoice, $amount, $paidAt, $reference, $submissionId): Payment {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($submissionId !== null) {
                $existingPayment = $lockedInvoice->payments()->where('submission_id', $submissionId)->first();

                if ($existingPayment !== null) {
                    if (
                        bccomp($existingPayment->amount, $amount, 2) !== 0
                        || $existingPayment->paid_at->toDateString() !== $paidAt
                        || $existingPayment->reference !== $reference
                    ) {
                        throw ValidationException::withMessages([
                            'submission_id' => 'This submission already recorded a payment with different details. Refresh the page and review the recorded payments before submitting again.',
                        ]);
                    }

                    return $existingPayment;
                }
            }

            $paid = '0.00';

            foreach ($lockedInvoice->payments()->cursor() as $payment) {
                $paid = bcadd($paid, $payment->amount, 2);
            }

            $balance = bcsub($lockedInvoice->total, $paid, 2);

            if (bccomp($amount, $balance, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment cannot exceed the outstanding balance.',
                ]);
            }

            $payment = $lockedInvoice->payments()->create([
                'amount' => $amount,
                'paid_at' => $paidAt,
                'reference' => $reference,
                'submission_id' => $submissionId,
            ]);

            $lockedInvoice->status = bccomp($amount, $balance, 2) === 0
                ? 'paid'
                : 'partial';

            $lockedInvoice->save();

            $tenantId = (string) $lockedInvoice->tenant_id;
            DB::afterCommit(fn () => DashboardCache::invalidate($tenantId));

            return $payment;
        });
    }
}
