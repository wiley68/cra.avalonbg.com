<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';
import type { Appearance } from '@/types';

const { appearance, updateAppearance } = useAppearance();
const { t } = useTranslations();

const options: Array<{
    value: Appearance;
    Icon: typeof Sun;
    labelKey: string;
}> = [
    { value: 'light', Icon: Sun, labelKey: 'appearance.light' },
    { value: 'dark', Icon: Moon, labelKey: 'appearance.dark' },
    { value: 'system', Icon: Monitor, labelKey: 'appearance.system' },
];
</script>

<template>
    <div
        role="group"
        :aria-label="t('appearance.label')"
        class="inline-flex overflow-hidden rounded-md border"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-label="t(option.labelKey)"
            :aria-pressed="appearance === option.value"
            :title="t(option.labelKey)"
            class="inline-flex items-center justify-center px-3 py-2 transition-colors not-first:border-l hover:bg-muted"
            :class="appearance === option.value ? 'bg-muted' : ''"
            @click="updateAppearance(option.value)"
        >
            <component :is="option.Icon" class="h-4 w-4" />
        </button>
    </div>
</template>
