<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
    status: 'AC' | 'DA';
}

interface CustomerPagination {
    data: Customer[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{
    customers: CustomerPagination;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: '/customers' },
        ],
    },
});

const statusForm = useForm({});

function deactivateCustomer(customer: Customer) {
    if (!window.confirm(`Deactivate customer "${customer.name}"?`)) {
        return;
    }

    statusForm.patch(`/customers/${customer.id}/deactivate`, {
        preserveScroll: true,
    });
}

function reactivateCustomer(customer: Customer) {
    if (!window.confirm(`Reactivate customer "${customer.name}"?`)) {
        return;
    }

    statusForm.patch(`/customers/${customer.id}/reactivate`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Customers" />

    <div class="space-y-4 p-4">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Customers</h1>

            <Link
                href="/customers/create"
                class="rounded-md bg-primary px-4 py-2 text-primary-foreground"
            >
                Add customer
            </Link>
        </div>

        <p v-if="customers.total === 0" class="text-muted-foreground">
            No customers yet.
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b">
                        <th class="p-3">Name</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Phone</th>
                        <th class="p-3">Address</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="customer in customers.data"
                        :key="customer.id"
                        class="border-b"
                    >
                        <td class="p-3">{{ customer.name }}</td>
                        <td class="p-3">{{ customer.email }}</td>
                        <td class="p-3">{{ customer.phone ?? '—' }}</td>
                        <td class="p-3">{{ customer.address ?? '—' }}</td>
                        <td class="p-3">{{ customer.status === 'AC' ? 'Active' : 'Deactivated' }}</td>
                        <td class="p-3">
                            <div class="flex items-center gap-4">
                                <Link
                                    :href="`/customers/${customer.id}/edit`"
                                    class="underline"
                                >
                                    Edit
                                </Link>

                                <button
                                    v-if="customer.status === 'AC'"
                                    type="button"
                                    class="text-red-600 underline disabled:opacity-50"
                                    :disabled="statusForm.processing"
                                    @click="deactivateCustomer(customer)"
                                >
                                    Deactivate
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="text-green-600 underline disabled:opacity-50"
                                    :disabled="statusForm.processing"
                                    @click="reactivateCustomer(customer)"
                                >
                                    Reactivate
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="customers.total > 0"
            aria-label="Customer pagination"
            class="flex items-center gap-4"
        >
            <Link
                v-if="customers.prev_page_url"
                :href="customers.prev_page_url"
                class="underline"
            >
                Previous
            </Link>

            <span>
                Page {{ customers.current_page }} of {{ customers.last_page }}
            </span>

            <Link
                v-if="customers.next_page_url"
                :href="customers.next_page_url"
                class="underline"
            >
                Next
            </Link>
        </nav>
    </div>
</template>