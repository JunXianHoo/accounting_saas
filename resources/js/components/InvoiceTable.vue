<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DateDisplay from './DateDisplay.vue';
import type { InvoiceSummary } from '@/types/invoice';

withDefaults(
    defineProps<{
        invoices: InvoiceSummary[];
        emptyMessage: string;
        showPaymentTotals?: boolean;
    }>(),
    {
        showPaymentTotals: true,
    },
);
</script>

<template>
    <div v-if="invoices.length > 0" class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b">
                <tr>
                    <th scope="col" class="p-2">Invoice no.</th>
                    <th scope="col" class="p-2">Customer</th>
                    <th scope="col" class="p-2">Issue date</th>
                    <th scope="col" class="p-2">Due date</th>
                    <th scope="col" class="p-2">Status</th>
                    <th scope="col" class="p-2 text-right">Total</th>
                    <th
                        v-if="showPaymentTotals"
                        scope="col"
                        class="p-2 text-right"
                    >
                        Paid to date
                    </th>
                    <th
                        v-if="showPaymentTotals"
                        scope="col"
                        class="p-2 text-right"
                    >
                        Outstanding
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    class="border-b"
                >
                    <td class="p-2">
                        <Link
                            :href="`/invoices/${invoice.id}`"
                            class="underline"
                        >
                            {{ invoice.invoice_number }}
                        </Link>
                    </td>
                    <td class="p-2">{{ invoice.customer?.name ?? '—' }}</td>
                    <td class="p-2">
                        <DateDisplay :value="invoice.issue_date" />
                    </td>
                    <td class="p-2">
                        <DateDisplay :value="invoice.due_date" />
                    </td>
                    <td class="p-2">
                        <span class="capitalize">{{ invoice.status }}</span>
                        <span
                            v-if="invoice.overdue"
                            class="ml-2 rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
                        >
                            Overdue
                        </span>
                    </td>
                    <td class="p-2 text-right tabular-nums">
                        {{ invoice.total }}
                    </td>
                    <td
                        v-if="showPaymentTotals"
                        class="p-2 text-right tabular-nums"
                    >
                        {{ invoice.total_paid }}
                    </td>
                    <td
                        v-if="showPaymentTotals"
                        class="p-2 text-right tabular-nums"
                    >
                        {{ invoice.outstanding_balance }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <p v-else class="text-muted-foreground">{{ emptyMessage }}</p>
</template>
