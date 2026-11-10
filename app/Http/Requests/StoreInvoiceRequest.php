<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * @return array{
     *     customer_id: string,
     *     issue_date: string,
     *     due_date: string,
     *     items: list<array{description: string, quantity: string, unit_price: string}>
     * }
     */
    public function invoiceData(): array
    {
        $data = $this->validated();

        return [
            'customer_id' => $data['customer_id'],
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'items' => $data['items'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && tenancy()->initialized;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'bail',
                'required',
                'ulid',
                Rule::exists('tenant.customers', 'id')
                    ->where('status', 'AC'),
            ],

            'issue_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:issue_date',
            ],

            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*' => [
                'required',
                'array:description,quantity,unit_price',
            ],
            'items.*.description' => ['required', 'string', 'max:2000'],
            'items.*.quantity' => [
                'required',
                'string',
                'numeric',
                'decimal:0,3',
                'min:0.001',
                'max:999999999.999',
            ],
            'items.*.unit_price' => [
                'required',
                'string',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:999999999999.99',
            ],

            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'total' => ['prohibited'],
        ];
    }
}
