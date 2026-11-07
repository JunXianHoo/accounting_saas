<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    label: string;
    value: string | number;
    href: string;
    query?: Record<string, string | number>;
}>();
</script>

<template>
    <div class="rounded-lg border p-4">
        <dt class="text-sm text-muted-foreground">{{ label }}</dt>
        <dd class="mt-2 text-2xl font-semibold tabular-nums">
            <Link
                :href="href"
                :data="query"
                :aria-label="`View ${label}: ${value}`"
                class="text-primary underline decoration-muted-foreground underline-offset-4 hover:decoration-current"
            >
                {{ value }}
            </Link>
        </dd>
    </div>
</template>
