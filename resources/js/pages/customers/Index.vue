<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import UBadge from '@nuxt/ui/components/Badge.vue';
import USelect from '@nuxt/ui/components/Select.vue';
import UTable from '@nuxt/ui/components/Table.vue';
import type { TableColumn } from '@nuxt/ui/components/Table.vue';
import { computed, useId, watch } from 'vue';

interface Customer {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
    status: 'AC' | 'DA';
    invoices_exists: boolean;
}

interface CustomerPagination {
    data: Customer[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    customers: CustomerPagination;
    filters: {
        name: string | null;
        status: Customer['status'] | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: '/customers' }],
    },
});

const columns: TableColumn<Customer>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        meta: { class: { td: 'font-medium text-highlighted' } },
    },
    { accessorKey: 'email', header: 'Email' },
    {
        accessorFn: (customer) => customer.phone ?? '—',
        id: 'phone',
        header: 'Phone',
    },
    {
        accessorFn: (customer) => customer.address ?? '—',
        id: 'address',
        header: 'Address',
        meta: { class: { td: 'min-w-40 whitespace-normal' } },
    },
    { accessorKey: 'status', header: 'Status' },
    { id: 'actions', header: 'Actions' },
];

const statusForm = useForm({});
const filterForm = useForm<{ name: string; status: Customer['status'] | '' }>({
    name: props.filters.name ?? '',
    status: props.filters.status ?? '',
});

const statusId = useId();
const statusOptions = [
    { label: 'All statuses', value: 'all' },
    { label: 'Active', value: 'AC' },
    { label: 'Deactivated', value: 'DA' },
] satisfies { label: string; value: 'all' | Customer['status'] }[];
const statusSelection = computed({
    get: () => filterForm.status || 'all',
    set: (value) => {
        filterForm.status = value === 'all' ? '' : value;
    },
});

watch(
    () => props.filters,
    (filters) => {
        filterForm.name = filters.name ?? '';
        filterForm.status = filters.status ?? '';
    },
);

function applyFilters() {
    filterForm.get('/customers', { preserveScroll: true });
}

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

function deleteCustomer(customer: Customer) {
    if (!window.confirm(`Permanently delete customer "${customer.name}"?`)) {
        return;
    }

    statusForm.delete(`/customers/${customer.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Customers" />

    <div class="space-y-4 p-4">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Customers</h1>

            <Button to="/customers/create">Add customer</Button>
        </div>

        <form
            class="flex flex-wrap items-start gap-4"
            @submit.prevent="applyFilters"
        >
            <label class="block w-full sm:max-w-xs">
                Customer name
                <Input
                    v-model="filterForm.name"
                    type="search"
                    maxlength="255"
                    placeholder="Search by name"
                    class="mt-1"
                />
                <span
                    v-if="filterForm.errors.name"
                    class="text-sm text-red-600"
                >
                    {{ filterForm.errors.name }}
                </span>
            </label>

            <div class="w-full sm:w-40">
                <label :for="statusId" class="block">Status</label>
                <USelect
                    :id="statusId"
                    v-model="statusSelection"
                    :items="statusOptions"
                    :aria-invalid="Boolean(filterForm.errors.status)"
                    :aria-describedby="
                        filterForm.errors.status
                            ? `${statusId}-error`
                            : undefined
                    "
                    class="mt-1 w-full"
                />
                <span
                    v-if="filterForm.errors.status"
                    :id="`${statusId}-error`"
                    class="text-sm text-red-600"
                >
                    {{ filterForm.errors.status }}
                </span>
            </div>

            <div class="flex items-center gap-2 pt-7">
                <Button type="submit" :disabled="filterForm.processing">
                    Apply
                </Button>
                <Button to="/customers" variant="outline">Reset</Button>
            </div>
        </form>

        <p
            v-if="$page.props.errors.customer"
            role="alert"
            class="text-sm text-red-600"
        >
            {{ $page.props.errors.customer }}
        </p>

        <UTable
            :data="customers.data"
            :columns="columns"
            :empty="
                filters.name || filters.status
                    ? 'No customers match your filters.'
                    : 'No customers yet.'
            "
            caption="Customers"
        >
            <template #status-cell="{ row }">
                <UBadge
                    :color="
                        row.original.status === 'AC' ? 'success' : 'neutral'
                    "
                    variant="subtle"
                >
                    {{
                        row.original.status === 'AC' ? 'Active' : 'Deactivated'
                    }}
                </UBadge>
            </template>
            <template #actions-cell="{ row }">
                <div class="flex items-center gap-2">
                    <Button
                        :to="`/customers/${row.original.id}/edit`"
                        variant="ghost"
                        size="sm"
                    >
                        Edit
                    </Button>
                    <Button
                        v-if="row.original.status === 'AC'"
                        type="button"
                        variant="ghost"
                        color="error"
                        size="sm"
                        :disabled="statusForm.processing"
                        @click="deactivateCustomer(row.original)"
                    >
                        Deactivate
                    </Button>
                    <Button
                        v-else
                        type="button"
                        variant="ghost"
                        color="success"
                        size="sm"
                        :disabled="statusForm.processing"
                        @click="reactivateCustomer(row.original)"
                    >
                        Reactivate
                    </Button>
                    <Button
                        v-if="!row.original.invoices_exists"
                        type="button"
                        variant="ghost"
                        color="error"
                        size="sm"
                        :disabled="statusForm.processing"
                        @click="deleteCustomer(row.original)"
                    >
                        Delete
                    </Button>
                </div>
            </template>
        </UTable>

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
