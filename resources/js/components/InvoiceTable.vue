<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import UBadge from '@nuxt/ui/components/Badge.vue';
import UTable from '@nuxt/ui/components/Table.vue';
import type { TableColumn } from '@nuxt/ui/components/Table.vue';
import DateDisplay from './DateDisplay.vue';
import { formatNumber } from '@/lib/formatNumber';
import type { InvoiceSummary } from '@/types/invoice';

defineProps<{
    invoices: InvoiceSummary[];
    emptyMessage: string;
}>();

const amount = { class: { th: 'text-end', td: 'text-end tabular-nums' } };
const columns: TableColumn<InvoiceSummary>[] = [
    { accessorKey: 'invoice_number', header: 'Invoice no.' },
    {
        accessorFn: (invoice) => invoice.customer?.name ?? '—',
        id: 'customer',
        header: 'Customer',
    },
    { accessorKey: 'issue_date', header: 'Issue date' },
    { accessorKey: 'due_date', header: 'Due date' },
    { accessorKey: 'status', header: 'Status' },
    {
        accessorKey: 'total',
        header: 'Total',
        cell: ({ row }) => formatNumber(row.original.total),
        meta: amount,
    },
    {
        accessorKey: 'total_paid',
        header: 'Paid to date',
        cell: ({ row }) => formatNumber(row.original.total_paid),
        meta: amount,
    },
    {
        accessorKey: 'outstanding_balance',
        header: 'Outstanding',
        cell: ({ row }) => formatNumber(row.original.outstanding_balance),
        meta: amount,
    },
];

const statusColors = {
    unpaid: 'neutral',
    partial: 'warning',
    paid: 'success',
} as const;
</script>

<template>
    <UTable
        :data="invoices"
        :columns="columns"
        :empty="emptyMessage"
        caption="Invoices"
    >
        <template #invoice_number-cell="{ row }">
            <Link
                :href="`/invoices/${row.original.id}`"
                class="font-medium text-primary hover:underline"
            >
                {{ row.original.invoice_number }}
            </Link>
        </template>
        <template #issue_date-cell="{ row }">
            <DateDisplay :value="row.original.issue_date" />
        </template>
        <template #due_date-cell="{ row }">
            <DateDisplay :value="row.original.due_date" />
        </template>
        <template #status-cell="{ row }">
            <div class="flex items-center gap-2">
                <UBadge
                    :color="statusColors[row.original.status]"
                    variant="subtle"
                    class="capitalize"
                >
                    {{ row.original.status }}
                </UBadge>
                <UBadge
                    v-if="row.original.overdue"
                    color="error"
                    variant="subtle"
                >
                    Overdue
                </UBadge>
            </div>
        </template>
    </UTable>
</template>
