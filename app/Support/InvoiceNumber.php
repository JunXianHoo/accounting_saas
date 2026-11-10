<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class InvoiceNumber
{
    public static function next(string $issueDate): string
    {
        $connection = DB::connection('tenant');

        return $connection->transaction(function () use ($connection, $issueDate): string {
            $sequence = $connection->table('invoice_number_sequences')
                ->where('issue_date', $issueDate);

            $connection->table('invoice_number_sequences')->insertOrIgnore([
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
