<?php

namespace App\Actions\Invoices;

use Illuminate\Validation\ValidationException;

class CalculateInvoiceTotals
{
    /**
     * @param  list<array{description: string, quantity: string, unit_price: string}>  $items
     * @return array{
     *     subtotal: numeric-string,
     *     items: list<array{description: string, quantity: string, unit_price: string, line_total: numeric-string}>
     * }
     */
    public function handle(array $items): array
    {
        $maximum = '999999999999.99';
        $subtotal = '0.00';
        $calculatedItems = [];

        foreach ($items as $index => $input) {
            if (! is_numeric($input['quantity']) || ! is_numeric($input['unit_price'])) {
                throw ValidationException::withMessages([
                    "items.$index" => 'Quantity and unit price must be numeric.',
                ]);
            }

            $rawTotal = bcmul(
                $input['quantity'],
                $input['unit_price'],
                5,
            );

            // Round non-negative amounts half up to two decimal places.
            $lineTotal = bcadd($rawTotal, '0.005', 2);

            if (bccomp($lineTotal, $maximum, 2) > 0) {
                throw ValidationException::withMessages([
                    "items.$index.unit_price" => 'This line total exceeds the supported limit.',
                ]);
            }

            $subtotal = bcadd($subtotal, $lineTotal, 2);

            if (bccomp($subtotal, $maximum, 2) > 0) {
                throw ValidationException::withMessages([
                    'items' => 'The invoice total exceeds the supported limit.',
                ]);
            }

            $calculatedItems[] = [
                ...$input,
                'line_total' => $lineTotal,
            ];
        }

        if (bccomp($subtotal, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'items' => 'The invoice total must be greater than zero.',
            ]);
        }

        return [
            'subtotal' => $subtotal,
            'items' => $calculatedItems,
        ];
    }
}
