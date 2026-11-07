<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ value: string }>();

const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

const date = computed(() => props.value.slice(0, 10));
const formattedDate = computed(() => {
    const [year, month, day] = date.value.split('-');

    return [day, months[Number(month) - 1], year].join('-');
});
</script>

<template>
    <time :datetime="date">{{ formattedDate }}</time>
</template>
