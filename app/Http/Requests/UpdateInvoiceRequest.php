<?php

namespace App\Http\Requests;

use App\Models\Invoice;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return parent::authorize()
            && $invoice instanceof Invoice
            && ! $invoice->payments()->exists();
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'invoice_number' => ['prohibited'],
        ];
    }
}
