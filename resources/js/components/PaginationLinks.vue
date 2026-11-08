<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    label: string;
    pagination: {
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>

<template>
    <nav v-if="pagination.last_page > 1" :aria-label="label" class="flex gap-4">
        <Link v-if="pagination.prev_page_url" :href="pagination.prev_page_url"
            >Previous</Link
        >
        <span
            >Page {{ pagination.current_page }} of
            {{ pagination.last_page }}</span
        >
        <Link v-if="pagination.next_page_url" :href="pagination.next_page_url"
            >Next</Link
        >
    </nav>
</template>
