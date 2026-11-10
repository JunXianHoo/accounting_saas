<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatNumber } from '@/lib/formatNumber';

const props = defineProps<{
    label: string;
    value: string | number;
    href: string;
    query?: Record<string, string | number>;
}>();

const displayValue = computed(() => formatNumber(props.value));
</script>

<template>
    <div class="rounded-lg border bg-card p-4 text-card-foreground">
        <dt class="text-sm text-muted-foreground">{{ label }}</dt>
        <dd class="mt-2 text-2xl font-semibold tabular-nums">
            <Link
                :href="href"
                :data="query"
                :aria-label="`View ${label}: ${displayValue}`"
                class="text-primary underline decoration-muted-foreground underline-offset-4 hover:decoration-current"
            >
                {{ displayValue }}
            </Link>
        </dd>
    </div>
</template>
