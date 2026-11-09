<?php

namespace Database\Seeders;

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Stancl\Tenancy\Database\Models\Tenant;

class DemoDataSeeder extends Seeder
{
    public function run(CreateInvoice $createInvoice, RecordPayment $recordPayment): void
    {
        if (! app()->environment('local', 'testing') || tenancy()->initialized) {
            throw new LogicException('Demo data must be seeded from the central context in a local or testing environment.');
        }

        foreach ([1 => 'Harbor Studio', 2 => 'Cedar Works'] as $companyNumber => $name) {
            $tenantId = "accounting-demo-{$companyNumber}";

            try {
                DB::transaction(function () use ($tenantId, $name, $companyNumber, $createInvoice, $recordPayment): void {
                    $tenant = Tenant::query()->firstOrCreate(['id' => $tenantId], ['name' => $name]);

                    if (! $tenant->wasRecentlyCreated) {
                        return;
                    }

                    $user = new User([
                        'name' => "{$name} Demo",
                        'email' => "demo{$companyNumber}@example.test",
                        'password' => 'accounting-demo-password',
                    ]);
                    $user->tenant()->associate($tenant);
                    $user->save();

                    tenancy()->initialize($tenant);

                    $activeCustomerIds = $this->createCustomers($companyNumber);
                    $this->createInvoices($activeCustomerIds, $companyNumber, $createInvoice, $recordPayment);
                });
            } finally {
                tenancy()->end();
            }
        }
    }

    /** @return list<int> */
    private function createCustomers(int $companyNumber): array
    {
        $activeCustomerIds = [];

        for ($number = 1; $number <= 200; $number++) {
            $active = $number % 10 !== 0;
            $customer = Customer::query()->forceCreate([
                'name' => sprintf('Demo Customer %03d', $number),
                'email' => "customer{$companyNumber}-{$number}@example.test",
                'status' => $active ? 'AC' : 'DA',
            ]);

            if ($active) {
                $activeCustomerIds[] = $customer->id;
            }
        }

        return $activeCustomerIds;
    }

    /** @param list<int> $activeCustomerIds */
    private function createInvoices(
        array $activeCustomerIds,
        int $companyNumber,
        CreateInvoice $createInvoice,
        RecordPayment $recordPayment,
    ): void {
        $today = today()->toImmutable();
        $customerCount = count($activeCustomerIds);

        for ($index = 0; $index < 2_000; $index++) {
            $customerIndex = ($index + intdiv($index, $customerCount)) % $customerCount;
            $issuedOn = $today->subDays(($index * $companyNumber) % 365);
            $invoice = $createInvoice->handle([
                'customer_id' => $activeCustomerIds[$customerIndex],
                'issue_date' => $issuedOn->toDateString(),
                'due_date' => $issuedOn->addDays($index % 2 === 0 ? 14 : 30)->toDateString(),
                'items' => [
                    ['description' => 'Consulting hours', 'quantity' => (string) (1 + $index % 12), 'unit_price' => $companyNumber === 1 ? '85.00' : '110.00'],
                    ['description' => 'Project materials', 'quantity' => (string) (1 + $index % 7), 'unit_price' => '24.95'],
                    ['description' => 'Service fee', 'quantity' => '1', 'unit_price' => '45.00'],
                ],
            ]);

            // One quarter remain unpaid, one quarter have a deposit, and half are settled.
            $paymentPattern = $index % 4;

            if ($paymentPattern === 0) {
                continue;
            }

            $deposit = bcdiv($invoice->total, '2', 2);
            $recordPayment->handle($invoice, $deposit, $issuedOn->addDays(3)->min($today)->toDateString(), 'DEPOSIT-'.$invoice->invoice_number);

            if ($paymentPattern > 1) {
                $balance = bcsub($invoice->total, $deposit, 2);
                $recordPayment->handle($invoice, $balance, $issuedOn->addDays(10)->min($today)->toDateString(), 'BALANCE-'.$invoice->invoice_number);
            }
        }
    }
}
