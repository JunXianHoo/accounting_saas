<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const paymentForm = useForm({
    amount: '',
    paid_at: props.latestPaymentDate,
    reference: '',
});

function submitPayment() {
    paymentForm.post(`/invoices/${props.invoice.id}/payments`, {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset('amount', 'reference'),
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
        <Link href="/invoices" class="underline">Back to invoices</Link>

        <h1 class="text-2xl font-semibold">
            Invoice {{ invoice.invoice_number }}
        </h1>
        <Link
            v-if="invoice.payments.length === 0"
            :href="`/invoices/${invoice.id}/edit`"
            class="inline-block cursor-pointer rounded border px-4 py-2 hover:bg-accent"
        >
            Edit invoice
        </Link>
        <Dialog v-if="invoice.payments.length === 0">
            <DialogTrigger as-child>
                <button
                    type="button"
                    class="cursor-pointer rounded border border-destructive px-4 py-2 text-destructive"
                >
                    Delete invoice
                </button>
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
                            <button
                                type="button"
                                class="cursor-pointer rounded border px-4 py-2 disabled:cursor-not-allowed"
                                :disabled="deleteForm.processing"
                            >
                                Cancel
                            </button>
                        </DialogClose>

                        <button
                            type="submit"
                            class="cursor-pointer rounded bg-destructive px-4 py-2 text-destructive-foreground disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="deleteForm.processing"
                        >
                            {{
                                deleteForm.processing
                                    ? 'Deleting…'
                                    : 'Delete invoice'
                            }}
                        </button>
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

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b">
                    <tr>
                        <th scope="col" class="p-2">Description</th>
                        <th scope="col" class="p-2 text-right">Quantity</th>
                        <th scope="col" class="p-2 text-right">Unit price</th>
                        <th scope="col" class="p-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in invoice.items"
                        :key="item.id"
                        class="border-b"
                    >
                        <td class="p-2">{{ item.description }}</td>
                        <td class="p-2 text-right">{{ item.quantity }}</td>
                        <td class="p-2 text-right">{{ item.unit_price }}</td>
                        <td class="p-2 text-right">{{ item.line_total }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3" class="p-2 text-right">
                            Invoice total
                        </th>
                        <td class="p-2 text-right font-semibold">
                            {{ invoice.total }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <section class="space-y-2 rounded border p-4">
        <h2 class="font-semibold">Payment history</h2>
        <p>Total paid: {{ totalPaid }}</p>
        <p>Outstanding balance: {{ outstandingBalance }}</p>

        <p v-if="invoice.payments.length === 0" class="text-muted-foreground">
            No payments recorded yet.
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b">
                    <tr>
                        <th scope="col" class="p-2">Payment date</th>
                        <th scope="col" class="p-2">Payment reference</th>
                        <th scope="col" class="p-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="payment in invoice.payments" :key="payment.id">
                        <td class="p-2">
                            <DateDisplay :value="payment.paid_at" />
                        </td>
                        <td class="p-2 break-words">
                            {{ payment.reference ?? '—' }}
                        </td>
                        <td class="p-2 text-right tabular-nums">
                            {{ payment.amount }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
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
            <input
                v-model="paymentForm.amount"
                type="text"
                inputmode="decimal"
                required
                class="mt-1 block w-full rounded border p-2"
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
            <input
                v-model="paymentForm.reference"
                type="text"
                maxlength="255"
                placeholder="Bank transaction, cheque, or receipt number"
                class="mt-1 block w-full rounded border p-2"
            />
            <span
                v-if="paymentForm.errors.reference"
                class="text-sm text-red-600"
            >
                {{ paymentForm.errors.reference }}
            </span>
        </label>

        <button
            type="submit"
            class="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
            :disabled="paymentForm.processing"
        >
            {{ paymentForm.processing ? 'Saving…' : 'Record payment' }}
        </button>
    </form>
</template>
