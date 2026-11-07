<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class InvoiceNumber
{
    public static function next(string $tenantId, string $issueDate): string
    {
        return DB::transaction(function () use ($tenantId, $issueDate): string {
            $sequence = DB::table('invoice_number_sequences')
                ->where('tenant_id', $tenantId)
                ->where('issue_date', $issueDate);

            DB::table('invoice_number_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'issue_date' => $issueDate,
                'last_number' => 0,
            ]);

            // Lock the counter so simultaneous invoices receive different numbers.
            $number = (int) (clone $sequence)->lockForUpdate()->value('last_number') + 1;
            $sequence->update(['last_number' => $number]);

            return 'INV'.str_replace('-', '', $issueDate).str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        }, 5);
    }
}
