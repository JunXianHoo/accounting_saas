<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DateRangePicker from './DateRangePicker.vue';
import type { InvoiceReportFilters } from '@/types/invoice';

const props = defineProps<{
    action: string;
    filters: InvoiceReportFilters;
    customers: { id: number; name: string }[];
    maxDate?: string;
}>();

function formValues(filters: InvoiceReportFilters) {
    return {
        ...filters,
        invoice_number: filters.invoice_number ?? '',
        status: filters.status ?? '',
        customer_id: String(filters.customer_id ?? ''),
    };
}

const form = useForm(formValues(props.filters));
const resetting = ref(false);

watch(() => props.filters, (filters) => {
    Object.assign(form, formValues(filters));
});

function applyFilters() {
    form.transform((data) => ({
        ...data,
        date_range: data.date_range ? 1 : 0,
        filter_by_invoice_number: data.filter_by_invoice_number ? 1 : 0,
    })).get(props.action, { preserveState: true });
}

function resetFilters() {
    router.get(props.action, {}, {
        preserveScroll: true,
        onStart: () => { resetting.value = true; },
        onFinish: () => { resetting.value = false; },
    });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="applyFilters">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                Payment Status
                <select v-model="form.status" class="mt-1 block w-full rounded border bg-background p-2">
                    <option value="">--All--</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
                <span v-if="form.errors.status" class="text-sm text-red-600">{{ form.errors.status }}</span>
            </label>

            <label class="block">
                Customer
                <select v-model="form.customer_id" class="mt-1 block w-full rounded border bg-background p-2">
                    <option value="">--All--</option>
                    <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">
                        {{ customer.name }}
                    </option>
                </select>
                <span v-if="form.errors.customer_id" class="text-sm text-red-600">{{ form.errors.customer_id }}</span>
            </label>
        </div>

        <div class="flex flex-wrap gap-4">
            <label class="flex cursor-pointer items-center gap-2">
                <input v-model="form.date_range" type="checkbox" />
                Date range
            </label>

            <label class="flex cursor-pointer items-center gap-2">
                <input v-model="form.filter_by_invoice_number" type="checkbox" />
                Invoice no.
            </label>
        </div>

        <label v-if="form.filter_by_invoice_number" class="block">
            Invoice no.
            <input
                v-model="form.invoice_number"
                type="text"
                required
                maxlength="20"
                placeholder="INV20261228001"
                class="mt-1 block w-full rounded border bg-background p-2"
            />
            <span v-if="form.errors.invoice_number" class="text-sm text-red-600">
                {{ form.errors.invoice_number }}
            </span>
        </label>

        <DateRangePicker
            v-if="form.date_range"
            v-model:from="form.from"
            v-model:to="form.to"
            :max-date="maxDate"
            :from-error="form.errors.from"
            :to-error="form.errors.to"
        />

        <div class="flex flex-wrap gap-2">
            <button
                type="submit"
                class="cursor-pointer rounded bg-primary px-4 py-2 text-primary-foreground disabled:cursor-not-allowed"
                :disabled="form.processing || resetting"
            >
                Apply
            </button>
            <button
                type="button"
                class="cursor-pointer rounded border px-4 py-2 hover:bg-accent disabled:cursor-not-allowed"
                :disabled="form.processing || resetting"
                @click="resetFilters"
            >
                Reset
            </button>
        </div>
    </form>
</template>
