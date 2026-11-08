<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
}

const props = defineProps<{
    customer: Customer;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: '/customers' }],
    },
});

const form = useForm({
    name: props.customer.name,
    email: props.customer.email,
    phone: props.customer.phone ?? '',
    address: props.customer.address ?? '',
});

function submit() {
    form.patch(`/customers/${props.customer.id}`);
}
</script>

<template>
    <Head title="Edit customer" />

    <div class="max-w-xl space-y-6 p-4">
        <h1 class="text-2xl font-semibold">Edit customer</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    autocomplete="name"
                    maxlength="255"
                    required
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    maxlength="255"
                    required
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Phone (optional)</Label>
                <Input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    autocomplete="tel"
                    maxlength="20"
                />
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address (optional)</Label>
                <textarea
                    id="address"
                    v-model="form.address"
                    autocomplete="street-address"
                    maxlength="255"
                    rows="3"
                    class="w-full rounded-md border bg-background p-3"
                />
                <InputError :message="form.errors.address" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save customer' }}
                </Button>

                <Link href="/customers" class="underline"> Cancel </Link>
            </div>
        </form>
    </div>
</template>
