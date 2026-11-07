<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import InvoiceTable from '@/components/InvoiceTable.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import type { PaginatedInvoices } from '@/types/invoice';

defineProps<{ invoices: PaginatedInvoices }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Invoices', href: '/invoices' }],
    },
});
</script>

<template>
    <Head title="Invoices" />

    <div class="space-y-4 p-4">
        <h1 class="text-2xl font-semibold">Invoices</h1>

        <Link
            href="/invoices/create"
            class="rounded bg-primary px-4 py-2 text-primary-foreground"
        >
            New invoice
        </Link>

        <InvoiceTable
            :invoices="invoices.data"
            :show-payment-totals="false"
            empty-message="No invoices yet."
        />
        <PaginationLinks :pagination="invoices" label="Invoice pages" />
    </div>
</template>
