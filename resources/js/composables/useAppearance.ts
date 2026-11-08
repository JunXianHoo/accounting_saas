import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const appearance = ref<Appearance>('system');
const systemPrefersDark = ref(false);
const resolvedAppearance = computed<ResolvedAppearance>(() => {
    if (appearance.value === 'system') {
        return systemPrefersDark.value ? 'dark' : 'light';
    }

    return appearance.value;
});

function applyTheme(): void {
    document.documentElement.classList.toggle(
        'dark',
        resolvedAppearance.value === 'dark',
    );
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const savedAppearance = localStorage.getItem('appearance');
    if (
        savedAppearance === 'light' ||
        savedAppearance === 'dark' ||
        savedAppearance === 'system'
    ) {
        appearance.value = savedAppearance;
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)');
    systemPrefersDark.value = media.matches;
    applyTheme();

    media.addEventListener('change', (event) => {
        systemPrefersDark.value = event.matches;
        applyTheme();
    });
}

export function useAppearance(): UseAppearanceReturn {
    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        document.cookie = `appearance=${value};path=/;max-age=31536000;SameSite=Lax`;
        applyTheme();
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
