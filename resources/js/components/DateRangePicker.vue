<script setup lang="ts">
import { computed } from 'vue';
import DatePicker from './DatePicker.vue';

const props = defineProps<{
    minDate?: string;
    maxDate?: string;
    fromError?: string;
    toError?: string;
}>();

const from = defineModel<string>('from', { required: true });
const to = defineModel<string>('to', { required: true });

const fromMaximum = computed(() =>
    [to.value, props.maxDate].filter((date): date is string => !!date).sort()[0],
);
const toMinimum = computed(() =>
    [from.value, props.minDate].filter((date): date is string => !!date).sort().at(-1),
);
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <DatePicker
            v-model="from"
            label="From"
            :min-date="minDate"
            :max-date="fromMaximum"
            :error="fromError"
        />
        <DatePicker
            v-model="to"
            label="To"
            :min-date="toMinimum"
            :max-date="maxDate"
            :error="toError"
        />
    </div>
</template>
