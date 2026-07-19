<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { edit } from '@/routes/appearance';

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'settings.appearance_title', href: edit() },
]);

</script>

<template>
    <Head :title="t('appearance.settings_title')" />

    <h1 class="sr-only">{{ t('appearance.settings_title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('appearance.settings_title')"
            :description="t('appearance.settings_description')"
        />
        <AppearanceTabs />
    </div>
</template>
