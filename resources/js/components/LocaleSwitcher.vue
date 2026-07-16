<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { locale, locales, t } = useTranslations();

const switchLocale = (event: Event): void => {
    const target = event.target as HTMLSelectElement;
    const nextLocale = target.value;

    if (nextLocale === locale.value) {
        return;
    }

    router.get(
        `/locale/${nextLocale}`,
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <label class="sr-only" for="locale-switcher">{{
        t('common.language')
    }}</label>
    <select
        id="locale-switcher"
        :value="locale"
        class="h-9 rounded-md border bg-background px-3 text-sm"
        @change="switchLocale"
    >
        <option
            v-for="option in locales"
            :key="option.code"
            :value="option.code"
        >
            {{ option.label }}
        </option>
    </select>
</template>
