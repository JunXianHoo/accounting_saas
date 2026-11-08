<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('customers/Index', [
            'customers' => Customer::query()
                ->select(['id', 'name', 'email', 'phone', 'address', 'status'])
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(15),
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

    /**
     * Remove the specified resource from storage.
     */
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
