<script setup lang="ts">
import UInput from '@nuxt/ui/components/Input.vue';
import { useVModel } from '@vueuse/core';
import { useTemplateRef } from 'vue';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    inputClass?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();

const value = useVModel(props, 'modelValue', emit, {
    passive: true,
    defaultValue: props.defaultValue,
});

const input = useTemplateRef('input');
defineExpose({ focus: () => input.value?.inputRef?.focus() });
</script>

<template>
    <UInput
        ref="input"
        v-model="value"
        class="w-full"
        :ui="{ base: inputClass }"
    />
</template>
