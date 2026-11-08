<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import UButton from '@nuxt/ui/components/Button.vue';
import { onMounted, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

defineProps<{ showLabel?: boolean }>();

const { resolvedAppearance, updateAppearance } = useAppearance();
const mounted = ref(false);

onMounted(() => {
    mounted.value = true;
});
</script>

<template>
    <UButton
        color="neutral"
        variant="ghost"
        :square="!showLabel"
        type="button"
        aria-label="Dark mode"
        :aria-pressed="mounted ? resolvedAppearance === 'dark' : undefined"
        @click="
            updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark')
        "
    >
        <Sun class="hidden size-4 dark:block" aria-hidden="true" />
        <Moon class="size-4 dark:hidden" aria-hidden="true" />
        <span v-if="showLabel">Dark mode</span>
    </UButton>
</template>
