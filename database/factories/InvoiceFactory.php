<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = today();
        return [
            'customer_id' => Customer::factory(),
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $issueDate->copy()->addDays(30)->toDateString(),
            'status' => 'unpaid',
            'subtotal' => '0.00',
            'total' => '0.00',
        ];
    }
}
