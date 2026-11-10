<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InvoiceForm from '@/components/InvoiceForm.vue';

defineProps<{
    customers: { id: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Invoice reports', href: '/reports/sales' },
            { title: 'New invoice', href: '/invoices/create' },
        ],
    },
});
</script>

<template>
    <Head title="New invoice" />

    <h1 class="px-4 pt-4 text-2xl font-semibold">New invoice</h1>

    <InvoiceForm :customers="customers" action="/invoices" />
</template>
