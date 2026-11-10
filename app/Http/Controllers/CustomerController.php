<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['AC', 'DA'])],
        ]);
        $filters = [
            'name' => $validated['name'] ?? null,
            'status' => $validated['status'] ?? null,
        ];

        $customers = Customer::query()
            ->select(['id', 'name', 'email', 'phone', 'address', 'status'])
            ->withExists('invoices');

        if ($filters['name'] !== null) {
            $name = strtr($filters['name'], ['!' => '!!', '%' => '!%', '_' => '!_']);
            $customers->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", ['%'.$name.'%']);
        }

        if ($filters['status'] !== null) {
            $customers->where('status', $filters['status']);
        }

        return Inertia::render('customers/Index', [
            'filters' => $filters,
            'customers' => $customers
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(15)
                ->appends($filters),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('customers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return to_route('customers.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/Edit', [
            'customer' => $customer->only([
                'id',
                'name',
                'email',
                'phone',
                'address',
            ]),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateCustomerRequest $request,
        Customer $customer
    ): RedirectResponse {
        $customer->update($request->validated());

        return to_route('customers.index');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        DB::transaction(function () use ($customer): void {
            $lockedCustomer = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedCustomer->invoices()->exists()) {
                throw ValidationException::withMessages([
                    'customer' => 'Customers with invoices can be deactivated instead.',
                ]);
            }

            $lockedCustomer->delete();
        });

        return to_route('customers.index');
    }

    public function deactivate(Customer $customer): RedirectResponse
    {
        $customer->status = 'DA';
        $customer->save();

        return to_route('customers.index');
    }

    public function reactivate(Customer $customer): RedirectResponse
    {
        $customer->status = 'AC';
        $customer->save();

        return to_route('customers.index');
    }
}
