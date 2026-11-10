<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { getLocalTimeZone, today } from '@internationalized/date';
import USelect from '@nuxt/ui/components/Select.vue';
import { computed, useId } from 'vue';
import InvoiceDatePicker from '@/components/DatePicker.vue';

interface InvoiceItem {
    description: string;
    quantity: string;
    unit_price: string;
}

interface InvoiceFormData {
    customer_id: string;
    issue_date: string;
    due_date: string;
    items: InvoiceItem[];
}

const props = withDefaults(
    defineProps<{
        customers: { id: string; name: string }[];
        action: string;
        method?: 'post' | 'patch';
        initialData?: InvoiceFormData;
        cancelHref?: string;
    }>(),
    {
        method: 'post',
        cancelHref: '/reports/sales',
    },
);

function newItem(): InvoiceItem {
    return { description: '', quantity: '1', unit_price: '' };
}

const defaultDate = today(getLocalTimeZone()).toString();

const form = useForm({
    customer_id: props.initialData?.customer_id ?? '',
    issue_date: props.initialData?.issue_date ?? defaultDate,
    due_date: props.initialData?.due_date ?? defaultDate,
    items: props.initialData
        ? props.initialData.items.map((item) => ({ ...item }))
        : [newItem()],
});

const customerId = useId();
const customerOptions = computed(() =>
    props.customers.map((customer) => ({
        label: customer.name,
        value: String(customer.id),
    })),
);

function submit() {
    form.submit(props.method, props.action);
}
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-4 p-4">
        <p v-if="customers.length === 0" class="text-muted-foreground">
            Add or reactivate a customer before saving an invoice.
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <p v-if="$page.props.errors.invoice" class="text-sm text-red-600">
                {{ $page.props.errors.invoice }}
            </p>

            <div>
                <label :for="customerId" class="block">Customer</label>
                <USelect
                    :id="customerId"
                    :model-value="form.customer_id || undefined"
                    :items="customerOptions"
                    name="customer_id"
                    placeholder="Select a customer"
                    required
                    :aria-invalid="Boolean(form.errors.customer_id)"
                    :aria-describedby="
                        form.errors.customer_id
                            ? `${customerId}-error`
                            : undefined
                    "
                    class="mt-1 w-full"
                    @update:model-value="form.customer_id = $event"
                />
                <span
                    v-if="form.errors.customer_id"
                    :id="`${customerId}-error`"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.customer_id }}
                </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <InvoiceDatePicker
                    v-model="form.issue_date"
                    label="Issue date"
                    :error="form.errors.issue_date"
                />
                <InvoiceDatePicker
                    v-model="form.due_date"
                    label="Due date"
                    :error="form.errors.due_date"
                />
            </div>

            <p v-if="form.errors.items" class="text-sm text-red-600">
                {{ form.errors.items }}
            </p>

            <fieldset
                v-for="(item, index) in form.items"
                :key="index"
                class="space-y-2 rounded border p-3"
            >
                <legend class="px-1 font-medium">Item {{ index + 1 }}</legend>

                <label class="block">
                    Description
                    <Input
                        v-model="item.description"
                        required
                        maxlength="2000"
                        class="mt-1"
                    />
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        Quantity
                        <Input
                            v-model="item.quantity"
                            inputmode="decimal"
                            required
                            class="mt-1"
                        />
                    </label>

                    <label>
                        Unit price
                        <Input
                            v-model="item.unit_price"
                            inputmode="decimal"
                            required
                            class="mt-1"
                        />
                    </label>
                </div>

                <p
                    v-for="field in [
                        'description',
                        'quantity',
                        'unit_price',
                    ] as const"
                    :key="field"
                    class="text-sm text-red-600"
                >
                    {{ form.errors[`items.${index}.${field}`] }}
                </p>

                <Button
                    v-if="form.items.length > 1"
                    type="button"
                    class="text-sm underline"
                    @click="form.items.splice(index, 1)"
                >
                    Remove item
                </Button>
            </fieldset>

            <Button
                type="button"
                variant="outline"
                :disabled="form.items.length >= 100"
                @click="form.items.push(newItem())"
            >
                Add item
            </Button>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing || customers.length === 0"
                >
                    {{ form.processing ? 'Saving…' : 'Save invoice' }}
                </Button>
                <Button :to="cancelHref" variant="ghost">Cancel</Button>
            </div>
        </form>
    </div>
</template>
