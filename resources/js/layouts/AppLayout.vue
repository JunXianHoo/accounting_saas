<script setup lang="ts">
import UApp from '@nuxt/ui/components/App.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <UApp :toaster="null">
        <AppLayout :breadcrumbs="breadcrumbs">
            <slot />
        </AppLayout>
    </UApp>
</template>
