<script setup lang="ts">
import { parseDate, type DateValue } from '@internationalized/date';
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarHeader,
    CalendarHeading,
    CalendarNext,
    CalendarPrev,
    CalendarRoot,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, ref, useId } from 'vue';

const props = defineProps<{
    label: string;
    error?: string;
    minDate?: string;
    maxDate?: string;
}>();

const model = defineModel<string>({ required: true });
const open = ref(false);
const id = useId();
const selected = computed(() => parseDate(model.value));
const minimum = computed(() =>
    props.minDate ? parseDate(props.minDate) : undefined,
);
const maximum = computed(() =>
    props.maxDate ? parseDate(props.maxDate) : undefined,
);
const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];
const formatted = computed(() => {
    const { day, month, year } = selected.value;

    return [String(day).padStart(2, '0'), months[month - 1], year].join('/  ');
});

function selectDate(date: DateValue | undefined) {
    if (date) {
        model.value = date.toString();
        open.value = false;
    }
}
</script>

<template>
    <div>
        <label :for="id">{{ label }}</label>
        <PopoverRoot v-model:open="open">
            <PopoverTrigger
                :id="id"
                type="button"
                :aria-label="`${label}: ${formatted}`"
                :aria-invalid="!!error"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="mt-1 block w-full cursor-pointer rounded border p-2 text-left"
            >
                {{ formatted }}
            </PopoverTrigger>
            <PopoverPortal>
                <PopoverContent
                    align="start"
                    :side-offset="4"
                    class="z-50 rounded-md border bg-popover p-3 text-popover-foreground shadow-md"
                >
                    <CalendarRoot
                        v-slot="{ grid, weekDays }"
                        :model-value="selected"
                        :min-value="minimum"
                        :max-value="maximum"
                        locale="en-GB"
                        prevent-deselect
                        initial-focus
                        @update:model-value="selectDate"
                    >
                        <CalendarHeader
                            class="mb-2 flex items-center justify-between gap-2"
                        >
                            <CalendarPrev
                                type="button"
                                class="cursor-pointer rounded px-2 py-1 hover:bg-accent disabled:cursor-not-allowed"
                                >‹</CalendarPrev
                            >
                            <CalendarHeading class="text-sm font-medium" />
                            <CalendarNext
                                type="button"
                                class="cursor-pointer rounded px-2 py-1 hover:bg-accent disabled:cursor-not-allowed"
                                >›</CalendarNext
                            >
                        </CalendarHeader>
                        <CalendarGrid
                            v-for="month in grid"
                            :key="month.value.toString()"
                        >
                            <CalendarGridHead>
                                <CalendarGridRow>
                                    <CalendarHeadCell
                                        v-for="day in weekDays"
                                        :key="day"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ day }}
                                    </CalendarHeadCell>
                                </CalendarGridRow>
                            </CalendarGridHead>
                            <CalendarGridBody>
                                <CalendarGridRow
                                    v-for="(week, index) in month.rows"
                                    :key="index"
                                >
                                    <CalendarCell
                                        v-for="day in week"
                                        :key="day.toString()"
                                        :date="day"
                                    >
                                        <CalendarCellTrigger
                                            :day="day"
                                            :month="month.value"
                                            class="grid size-9 cursor-pointer place-items-center rounded text-sm hover:bg-accent focus-visible:outline-2 data-[disabled]:cursor-not-allowed data-[outside-view]:text-muted-foreground data-[selected]:bg-primary data-[selected]:text-primary-foreground"
                                        />
                                    </CalendarCell>
                                </CalendarGridRow>
                            </CalendarGridBody>
                        </CalendarGrid>
                    </CalendarRoot>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
        <p v-if="error" :id="`${id}-error`" class="text-sm text-red-600">
            {{ error }}
        </p>
    </div>
</template>
