<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InvoiceReportFilters from '@/components/InvoiceReportFilters.vue';
import InvoiceTable from '@/components/InvoiceTable.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import type {
    InvoiceReportFilters as Filters,
    PaginatedInvoices,
} from '@/types/invoice';

defineProps<{
    filters: Filters;
    latestReportDate: string;
    customers: { id: number; name: string }[];
    salesTotal: string;
    paidTotal: string;
    outstandingTotal: string;
    invoices: PaginatedInvoices;
}>();
</script>

<template>
    <Head title="Invoice reports" />

    <div class="space-y-4 p-4">
        <h1 class="text-2xl font-semibold">Invoice reports</h1>

        <p v-if="filters.balance" class="text-sm text-muted-foreground">
            {{
                filters.balance === 'overdue'
                    ? 'Showing overdue balances'
                    : 'Showing outstanding balances'
            }}
        </p>

        <InvoiceReportFilters
            action="/reports/sales"
            :filters="filters"
            :customers="customers"
            :max-date="latestReportDate"
        />

        <dl class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">Total invoiced</dt>
                <dd class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ salesTotal }}
                </dd>
            </div>
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">Paid to date</dt>
                <dd class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ paidTotal }}
                </dd>
            </div>
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">Outstanding</dt>
                <dd class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ outstandingTotal }}
                </dd>
            </div>
        </dl>

        <InvoiceTable
            :invoices="invoices.data"
            empty-message="No invoices found."
        />
        <PaginationLinks :pagination="invoices" label="Invoice report pages" />
    </div>
</template>
