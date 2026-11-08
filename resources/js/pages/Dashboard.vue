<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DashboardMetricCard from '@/components/DashboardMetricCard.vue';
import { dashboard } from '@/routes';

defineProps<{
    invoiceCounts: {
        total: number;
        unpaid: number;
        partial: number;
        paid: number;
        overdue: number;
    };
    receivedThisMonth: string;
    invoiceBalances: {
        outstanding: string;
        overdue: string;
        paid: string;
    };
    currentMonth: { from: string; to: string };
}>();

const reportUrl = '/reports/sales';

const countCards = [
    { key: 'total', label: 'Total invoices' },
    { key: 'unpaid', label: 'Unpaid', status: 'unpaid' },
    { key: 'partial', label: 'Partial', status: 'partial' },
    { key: 'paid', label: 'Paid', status: 'paid' },
    { key: 'overdue', label: 'Overdue', balance: 'overdue' },
] as const;

const balanceCards = [
    { key: 'outstanding', label: 'Outstanding balance' },
    { key: 'overdue', label: 'Overdue balance' },
    { key: 'paid', label: 'Paid invoice total' },
] as const;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-4 p-4">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Invoice overview</h1>
            <Link href="/invoices" class="underline">View invoices</Link>
        </div>

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <DashboardMetricCard
                v-for="card in countCards"
                :key="card.key"
                :label="card.label"
                :value="invoiceCounts[card.key]"
                :href="reportUrl"
                :query="
                    'status' in card
                        ? { status: card.status }
                        : 'balance' in card
                          ? { balance: card.balance }
                          : undefined
                "
            />
        </dl>

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <DashboardMetricCard
                label="Payments received this month"
                :value="receivedThisMonth"
                :href="reportUrl"
                :query="{ status: 'paid', date_range: 1, ...currentMonth }"
            />

            <DashboardMetricCard
                v-for="card in balanceCards"
                :key="card.key"
                :label="card.label"
                :value="invoiceBalances[card.key]"
                :href="reportUrl"
                :query="
                    card.key === 'paid'
                        ? { status: 'paid' }
                        : { balance: card.key }
                "
            />
        </dl>
    </div>
</template>
