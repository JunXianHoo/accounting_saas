<script setup lang="ts">
import UButton from '@nuxt/ui/components/Button.vue';

withDefaults(defineProps<{
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    size?: 'default' | 'sm' | 'lg' | 'icon' | 'icon-sm' | 'icon-lg';
    color?: 'primary' | 'neutral' | 'error' | 'success';
}>(), {
    variant: 'default',
    size: 'default',
});

const variants = {
    default: { color: 'primary', variant: 'solid' },
    destructive: { color: 'error', variant: 'solid' },
    outline: { color: 'neutral', variant: 'outline' },
    secondary: { color: 'neutral', variant: 'soft' },
    ghost: { color: 'neutral', variant: 'ghost' },
    link: { color: 'primary', variant: 'link' },
} as const;

const sizes = {
    default: 'md',
    sm: 'sm',
    lg: 'lg',
    icon: 'md',
    'icon-sm': 'sm',
    'icon-lg': 'lg',
} as const;
</script>

<template>
    <UButton
        :color="color ?? variants[variant].color"
        :variant="variants[variant].variant"
        :size="sizes[size]"
        :square="size.startsWith('icon')"
        class="justify-center [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
    >
        <slot />
    </UButton>
</template>
