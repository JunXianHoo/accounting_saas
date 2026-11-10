<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import USelect from '@nuxt/ui/components/Select.vue';
import USelectMenu from '@nuxt/ui/components/SelectMenu.vue';
import { computed, ref, useId, watch } from 'vue';
import DateRangePicker from './DateRangePicker.vue';
import type { InvoiceReportFilters } from '@/types/invoice';

const props = defineProps<{
    action: string;
    filters: InvoiceReportFilters;
    customers: { id: string; name: string }[];
    maxDate?: string;
}>();

function formValues(filters: InvoiceReportFilters) {
    const status: NonNullable<InvoiceReportFilters['status']> | '' =
        filters.status ?? '';

    return {
        ...filters,
        overdueOnly: filters.balance === 'overdue',
        invoice_number: filters.invoice_number ?? '',
        status,
        customer_id: String(filters.customer_id ?? ''),
    };
}

const form = useForm(formValues(props.filters));
const resetting = ref(false);
const customerInputId = useId();
const statusId = useId();
const statusOptions = [
    { label: 'All statuses', value: 'all' },
    { label: 'Unpaid', value: 'unpaid' },
    { label: 'Partial', value: 'partial' },
    { label: 'Paid', value: 'paid' },
] satisfies {
    label: string;
    value: 'all' | NonNullable<InvoiceReportFilters['status']>;
}[];
const statusSelection = computed({
    get: () => form.status || 'all',
    set: (value) => {
        form.status = value === 'all' ? '' : value;
    },
});
const customerOptions = computed(() => [
    { value: 'all', label: 'All customers' },
    ...props.customers.map((customer) => ({
        value: String(customer.id),
        label: customer.name,
    })),
]);
const customerSelection = computed({
    get: () => form.customer_id || 'all',
    set: (value: string) => {
        form.customer_id = value === 'all' ? '' : value;
    },
});

watch(
    () => props.filters,
    (filters) => {
        Object.assign(form, formValues(filters));
    },
);

function applyFilters() {
    form.transform(({ overdueOnly, ...data }) => {
        const outstandingBalance =
            data.balance === 'outstanding' ? data.balance : null;

        return {
            ...data,
            balance: overdueOnly ? 'overdue' : outstandingBalance,
            date_range: data.date_range ? 1 : 0,
            filter_by_invoice_number: data.filter_by_invoice_number ? 1 : 0,
        };
    }).get(props.action, { preserveState: true });
}

function resetFilters() {
    router.get(
        props.action,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                resetting.value = true;
            },
            onFinish: () => {
                resetting.value = false;
            },
        },
    );
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="applyFilters">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label :for="statusId" class="block">Payment Status</label>
                <USelect
                    :id="statusId"
                    v-model="statusSelection"
                    :items="statusOptions"
                    :aria-invalid="Boolean(form.errors.status)"
                    :aria-describedby="
                        form.errors.status ? `${statusId}-error` : undefined
                    "
                    class="mt-1 w-full"
                />
                <span
                    v-if="form.errors.status"
                    :id="`${statusId}-error`"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.status }}
                </span>
            </div>

            <div>
                <label :for="customerInputId" class="block">Customer</label>
                <USelectMenu
                    :id="customerInputId"
                    v-model="customerSelection"
                    :items="customerOptions"
                    value-key="value"
                    :search-input="{ placeholder: 'Search customers…' }"
                    :aria-invalid="Boolean(form.errors.customer_id)"
                    :aria-describedby="
                        form.errors.customer_id
                            ? `${customerInputId}-error`
                            : undefined
                    "
                    class="mt-1 w-full"
                >
                    <template #empty>No customers found.</template>
                </USelectMenu>
                <span
                    v-if="form.errors.customer_id"
                    :id="`${customerInputId}-error`"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.customer_id }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap gap-4">
            <label class="flex cursor-pointer items-center gap-2">
                <input v-model="form.date_range" type="checkbox" />
                Date range
            </label>

            <label class="flex cursor-pointer items-center gap-2">
                <input
                    v-model="form.filter_by_invoice_number"
                    type="checkbox"
                />
                Invoice no.
            </label>

            <label class="flex cursor-pointer items-center gap-2">
                <input v-model="form.overdueOnly" type="checkbox" />
                Overdue only
            </label>
        </div>

        <label v-if="form.filter_by_invoice_number" class="block">
            Invoice no.
            <Input
                v-model="form.invoice_number"
                type="text"
                required
                maxlength="20"
                placeholder="INV20261228001"
                class="mt-1"
            />
            <span
                v-if="form.errors.invoice_number"
                class="text-sm text-red-600"
            >
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
            <Button type="submit" :disabled="form.processing || resetting">
                Apply
            </Button>
            <Button
                type="button"
                variant="outline"
                :disabled="form.processing || resetting"
                @click="resetFilters"
            >
                Reset
            </Button>
        </div>
    </form>
</template>
