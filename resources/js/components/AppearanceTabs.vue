<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import UButton from '@nuxt/ui/components/Button.vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        class="inline-flex flex-wrap gap-1 rounded-lg bg-elevated p-1"
        role="group"
        aria-label="Appearance"
    >
        <UButton
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :aria-pressed="appearance === value"
            :variant="appearance === value ? 'solid' : 'ghost'"
            color="neutral"
            type="button"
        >
            <component :is="Icon" class="size-4" aria-hidden="true" />
            {{ label }}
        </UButton>
    </div>
</template>
