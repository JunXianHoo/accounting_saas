<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
    autofocus?: boolean;
}>();

const showPassword = ref(false);
const inputRef = useTemplateRef('inputRef');

defineExpose({
    focus: () => inputRef.value?.focus(),
});
</script>

<template>
    <div class="relative">
        <Input
            :autofocus="props.autofocus"
            ref="inputRef"
            :type="showPassword ? 'text' : 'password'"
            :class="props.class"
            input-class="pr-10"
            v-bind="$attrs"
        />
        <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            @click="showPassword = !showPassword"
            class="absolute inset-y-0 right-1 my-auto text-muted-foreground"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            :tabindex="-1"
        >
            <EyeOff v-if="showPassword" class="size-4" />
            <Eye v-else class="size-4" />
        </Button>
    </div>
</template>
