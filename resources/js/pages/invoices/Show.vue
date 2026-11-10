<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import UTable from '@nuxt/ui/components/Table.vue';
import type { TableColumn } from '@nuxt/ui/components/Table.vue';
import DateDisplay from '@/components/DateDisplay.vue';
import InvoiceDatePicker from '@/components/DatePicker.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

interface InvoiceItem {
    id: number;
    description: string;
    quantity: string;
    unit_price: string;
    line_total: string;
}

const props = defineProps<{
    invoice: {
        id: number;
        invoice_number: string;
        customer: { id: number; name: string } | null;
        issue_date: string;
        due_date: string;
        status: 'unpaid' | 'partial' | 'paid';
        overdue: boolean;
        total: string;
        items: InvoiceItem[];
        payments: {
            id: number;
            amount: string;
            paid_at: string;
            reference: string | null;
        }[];
    };
    totalPaid: string;
    outstandingBalance: string;
    latestPaymentDate: string;
}>();

const amount = { class: { th: 'text-end', td: 'text-end tabular-nums' } };
const itemColumns: TableColumn<InvoiceItem>[] = [
    {
        accessorKey: 'description',
        header: 'Description',
        footer: 'Invoice total',
        meta: { class: { td: 'min-w-48 whitespace-normal' } },
    },
    { accessorKey: 'quantity', header: 'Quantity', meta: amount },
    { accessorKey: 'unit_price', header: 'Unit price', meta: amount },
    {
        accessorKey: 'line_total',
        header: 'Total',
        footer: () => props.invoice.total,
        meta: amount,
    },
];

const paymentColumns: TableColumn<(typeof props.invoice.payments)[number]>[] = [
    { accessorKey: 'paid_at', header: 'Payment date' },
    {
        accessorFn: (payment) => payment.reference ?? '—',
        id: 'reference',
        header: 'Payment reference',
        meta: { class: { td: 'min-w-40 whitespace-normal break-words' } },
    },
    { accessorKey: 'amount', header: 'Amount', meta: amount },
];

const paymentForm = useForm({
    amount: '',
    paid_at: props.latestPaymentDate,
    reference: '',
    submission_id: '',
});

function submitPayment() {
    if (paymentForm.processing) {
        return;
    }

    paymentForm.submission_id ||= crypto.randomUUID();

    paymentForm.post(`/invoices/${props.invoice.id}/payments`, {
        preserveScroll: true,
        onSuccess: () =>
            paymentForm.reset('amount', 'reference', 'submission_id'),
    });
}

const deleteForm = useForm({});

function deleteInvoice() {
    deleteForm.delete(`/invoices/${props.invoice.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />

    <div class="space-y-4 p-4">
        <Link href="/reports/sales" class="underline">
            Back to invoice reports
        </Link>

        <h1 class="text-2xl font-semibold">
            Invoice {{ invoice.invoice_number }}
        </h1>
        <Button
            v-if="invoice.payments.length === 0"
            :to="`/invoices/${invoice.id}/edit`"
            variant="outline"
        >
            Edit invoice
        </Button>
        <Dialog v-if="invoice.payments.length === 0">
            <DialogTrigger as-child>
                <Button type="button" variant="outline" color="error">
                    Delete invoice
                </Button>
            </DialogTrigger>

            <DialogContent>
                <form class="space-y-4" @submit.prevent="deleteInvoice">
                    <DialogHeader>
                        <DialogTitle>Delete invoice?</DialogTitle>
                        <DialogDescription>
                            Invoice {{ invoice.invoice_number }} and its items
                            will be permanently deleted. This action cannot be
                            undone.
                        </DialogDescription>
                    </DialogHeader>

                    <p
                        v-if="$page.props.errors.invoice"
                        class="text-sm text-red-600"
                    >
                        {{ $page.props.errors.invoice }}
                    </p>

                    <DialogFooter>
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="deleteForm.processing"
                            >
                                Cancel
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="deleteForm.processing"
                        >
                            {{
                                deleteForm.processing
                                    ? 'Deleting…'
                                    : 'Delete invoice'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        <p>Customer: {{ invoice.customer?.name ?? '—' }}</p>
        <p>Issue date: <DateDisplay :value="invoice.issue_date" /></p>
        <p>Due date: <DateDisplay :value="invoice.due_date" /></p>
        <p>
            Status: <span class="capitalize">{{ invoice.status }}</span>
            <span
                v-if="invoice.overdue"
                class="ml-2 rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
            >
                Overdue
            </span>
        </p>

        <UTable
            :data="invoice.items"
            :columns="itemColumns"
            caption="Invoice items"
        />
    </div>

    <section class="space-y-2 rounded border p-4">
        <h2 class="font-semibold">Payment history</h2>
        <p>Total paid: {{ totalPaid }}</p>
        <p>Outstanding balance: {{ outstandingBalance }}</p>

        <UTable
            :data="invoice.payments"
            :columns="paymentColumns"
            empty="No payments recorded yet."
            caption="Payment history"
        >
            <template #paid_at-cell="{ row }">
                <DateDisplay :value="row.original.paid_at" />
            </template>
        </UTable>
    </section>

    <form
        v-if="
            invoice.status !== 'paid' &&
            invoice.issue_date.slice(0, 10) <= latestPaymentDate
        "
        class="max-w-md space-y-3 rounded border p-4"
        @submit.prevent="submitPayment"
    >
        <h2 class="font-semibold">Record payment</h2>

        <label class="block">
            Amount
            <Input
                v-model="paymentForm.amount"
                type="text"
                inputmode="decimal"
                required
                class="mt-1"
            />
            <span v-if="paymentForm.errors.amount" class="text-sm text-red-600">
                {{ paymentForm.errors.amount }}
            </span>
        </label>

        <InvoiceDatePicker
            v-model="paymentForm.paid_at"
            label="Payment date"
            :min-date="invoice.issue_date.slice(0, 10)"
            :max-date="latestPaymentDate"
            :error="paymentForm.errors.paid_at"
        />

        <label class="block">
            Payment reference (optional)
            <Input
                v-model="paymentForm.reference"
                type="text"
                maxlength="255"
                placeholder="Bank transaction, cheque, or receipt number"
                class="mt-1"
            />
            <span
                v-if="paymentForm.errors.reference"
                class="text-sm text-red-600"
            >
                {{ paymentForm.errors.reference }}
            </span>
        </label>

        <p v-if="paymentForm.errors.submission_id" class="text-sm text-red-600">
            {{ paymentForm.errors.submission_id }}
        </p>

        <Button type="submit" :disabled="paymentForm.processing">
            {{ paymentForm.processing ? 'Saving…' : 'Record payment' }}
        </Button>
    </form>
</template>
