<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function sales(Request $request): Response
    {
        $today = today();
        $dateRange = $request->boolean('date_range');
        $defaultFrom = $today->copy()->startOfMonth()->toDateString();
        $defaultTo = $today->toDateString();

        $filters = validator([
            'from' => $dateRange ? $request->query('from', $defaultFrom) : $defaultFrom,
            'to' => $dateRange ? $request->query('to', $defaultTo) : $defaultTo,
            'date_range' => $request->query('date_range', false),
            'filter_by_invoice_number' => $request->query('filter_by_invoice_number', false),
            'invoice_number' => $request->boolean('filter_by_invoice_number') ? $request->query('invoice_number') : null,
            'status' => $request->query('status'),
            'balance' => $request->query('balance'),
            'customer_id' => $request->query('customer_id'),
        ], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$today->toDateString()],
            'date_range' => ['boolean'],
            'filter_by_invoice_number' => ['boolean'],
            'invoice_number' => [
                Rule::requiredIf($request->boolean('filter_by_invoice_number')),
                'nullable',
                'string',
                'max:20',
            ],
            'status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid'])],
            'balance' => ['nullable', Rule::in(['outstanding', 'overdue'])],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', tenant()->getTenantKey()),
            ],
        ])->validate();

        $filters['date_range'] = (bool) $filters['date_range'];
        $filters['filter_by_invoice_number'] = (bool) $filters['filter_by_invoice_number'];

        $invoices = Invoice::query();

        if ($filters['filter_by_invoice_number']) {
            $invoices->where('invoice_number', $filters['invoice_number']);
        }

        if ($filters['date_range']) {
            $dayAfterEnd = Carbon::parse($filters['to'])->addDay()->toDateString();

            $invoices->where('issue_date', '>=', $filters['from'])
                ->where('issue_date', '<', $dayAfterEnd);
        }

        if ($filters['status'] !== null) {
            $invoices->where('status', $filters['status']);
        }

        if ($filters['balance'] !== null) {
            $payments = Payment::query()
                ->selectRaw('ROUND(COALESCE(SUM(amount), 0), 2)')
                ->whereColumn('payments.invoice_id', 'invoices.id')
                ->toBase();

            $invoices->where('invoices.total', '>', $payments);

            if ($filters['balance'] === 'overdue') {
                $invoices->where('due_date', '<', $today->toDateString());
            }
        }

        if ($filters['customer_id'] !== null) {
            $invoices->where('customer_id', $filters['customer_id']);
        }

        $matchingInvoices = (clone $invoices)
            ->select(['invoices.id', 'invoices.total'])
            ->withPaymentTotals();

        $totals = DB::query()
            ->fromSub($matchingInvoices->toBase(), 'matching_invoices')
            ->selectRaw('COALESCE(SUM(total), 0) AS invoiced, COALESCE(SUM(total_paid), 0) AS paid')
            ->first();

        $salesTotal = Decimal::format($totals->invoiced);
        $paidTotal = Decimal::format($totals->paid);

        if ($filters['balance'] !== null) {
            $invoices->orderBy('due_date')->orderBy('id');
        } else {
            $invoices->orderByDesc('issue_date')->orderByDesc('id');
        }

        return Inertia::render('reports/Sales', [
            'filters' => $filters,
            'latestReportDate' => $today->toDateString(),
            'customers' => Customer::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'salesTotal' => $salesTotal,
            'paidTotal' => $paidTotal,
            'outstandingTotal' => bcsub($salesTotal, $paidTotal, 2),
            'invoices' => $invoices
                ->with('customer:id,name')
                ->withPaymentTotals()
                ->paginate(15)
                ->appends($filters)
                ->through(fn (Invoice $invoice) => $invoice->appendBalance()),
        ]);
    }

    public function outstanding(): RedirectResponse
    {
        return to_route('reports.sales', ['balance' => 'outstanding']);
    }
}
