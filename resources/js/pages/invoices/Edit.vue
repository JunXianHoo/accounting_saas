<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InvoiceForm from '@/components/InvoiceForm.vue';

const props = defineProps<{
    invoice: {
        id: number;
        invoice_number: string;
        customer_id: number;
        issue_date: string;
        due_date: string;
        items: {
            description: string;
            quantity: string;
            unit_price: string;
        }[];
    };
    customers: { id: number; name: string }[];
}>();

const initialData = {
    customer_id: String(props.invoice.customer_id),
    issue_date: props.invoice.issue_date,
    due_date: props.invoice.due_date,
    items: props.invoice.items,
};
</script>

<template>
    <Head :title="`Edit ${invoice.invoice_number}`" />

    <h1 class="px-4 pt-4 text-2xl font-semibold">
        Edit invoice {{ invoice.invoice_number }}
    </h1>

    <InvoiceForm
        :key="invoice.id"
        :customers="customers"
        :action="`/invoices/${invoice.id}`"
        method="patch"
        :initial-data="initialData"
        :cancel-href="`/invoices/${invoice.id}`"
    />
</template>
