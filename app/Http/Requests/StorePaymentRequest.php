<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && tenancy()->initialized;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');

        if (! $invoice instanceof Invoice) {
            abort(404);
        }

        return [
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => [
                'bail',
                'required',
                'string',
                'regex:/\A[0-9]{1,12}(?:\.[0-9]{1,2})?\z/',
                'numeric',
                'gt:0',
            ],
            'paid_at' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$invoice->issue_date->toDateString(),
                'before_or_equal:today',
            ],
        ];
    }
}
