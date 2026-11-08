<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref, useId, watch } from 'vue';
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
        overdueOnly: filters.balance === 'overdue',
        invoice_number: filters.invoice_number ?? '',
        status: filters.status ?? '',
        customer_id: String(filters.customer_id ?? ''),
    };
}

const form = useForm(formValues(props.filters));
const resetting = ref(false);
const customerInputId = useId();
const customerOptions = computed(() => [
    { value: 'all', label: '-- All --' },
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

function customerLabel(value: string) {
    return (
        customerOptions.value.find((option) => option.value === value)?.label ??
        ''
    );
}

function selectCustomerSearchText(event: FocusEvent) {
    if (event.target instanceof HTMLInputElement) {
        event.target.select();
    }
}

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
            <label class="block">
                Payment Status
                <select
                    v-model="form.status"
                    class="mt-1 block w-full rounded border bg-background p-2"
                >
                    <option value="">--All--</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
                <span v-if="form.errors.status" class="text-sm text-red-600">{{
                    form.errors.status
                }}</span>
            </label>

            <div>
                <label :for="customerInputId">Customer</label>
                <ComboboxRoot v-model="customerSelection" open-on-click>
                    <ComboboxAnchor as-child>
                        <div
                            class="mt-1 flex items-center rounded border bg-background focus-within:ring-2 focus-within:ring-ring"
                        >
                            <ComboboxInput
                                :id="customerInputId"
                                :display-value="customerLabel"
                                :aria-invalid="Boolean(form.errors.customer_id)"
                                :aria-describedby="
                                    form.errors.customer_id
                                        ? `${customerInputId}-error`
                                        : undefined
                                "
                                placeholder="Search customers…"
                                class="min-w-0 flex-1 bg-transparent p-2 outline-none"
                                @focus="selectCustomerSearchText"
                                @keydown.enter.prevent
                            />
                            <ComboboxTrigger
                                class="cursor-pointer px-2"
                                aria-label="Show customers"
                            >
                                <ChevronDown
                                    class="size-4 opacity-50"
                                    aria-hidden="true"
                                />
                            </ComboboxTrigger>
                        </div>
                    </ComboboxAnchor>
                    <ComboboxPortal>
                        <ComboboxContent
                            position="popper"
                            align="start"
                            :side-offset="4"
                            class="z-50 w-[var(--reka-combobox-trigger-width)] rounded border bg-popover text-popover-foreground shadow-md"
                        >
                            <ComboboxViewport
                                class="max-h-60 overflow-y-auto p-1"
                            >
                                <ComboboxEmpty
                                    class="px-2 py-1.5 text-sm text-muted-foreground"
                                >
                                    No customers found.
                                </ComboboxEmpty>
                                <ComboboxItem
                                    v-for="option in customerOptions"
                                    :key="option.value"
                                    :value="option.value"
                                    :text-value="option.label"
                                    class="cursor-pointer rounded px-2 py-1.5 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[state=checked]:font-semibold"
                                >
                                    {{ option.label }}
                                </ComboboxItem>
                            </ComboboxViewport>
                        </ComboboxContent>
                    </ComboboxPortal>
                </ComboboxRoot>
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
            <input
                v-model="form.invoice_number"
                type="text"
                required
                maxlength="20"
                placeholder="INV20261228001"
                class="mt-1 block w-full rounded border bg-background p-2"
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
