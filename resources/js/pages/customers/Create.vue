<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: '/customers' },
            { title: 'Add customer', href: '/customers/create' },
        ],
    },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
});
</script>

<template>
    <Head title="Add customer" />

    <div class="max-w-xl space-y-6 p-4">
        <h1 class="text-2xl font-semibold">Add customer</h1>

        <form
            class="space-y-4"
            @submit.prevent="form.post('/customers')"
        >
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

                <Link href="/customers" class="underline">
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>