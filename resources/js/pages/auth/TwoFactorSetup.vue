<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

type Props = {
    canManageTwoFactor: boolean;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
};

defineProps<Props>();

const { t } = useTranslations();

defineOptions({
    layout: {
        titleKey: 'auth.two_factor_setup.title',
        descriptionKey: 'auth.two_factor_setup.description',
    },
});
</script>

<template>
    <Head :title="t('auth.two_factor_setup.head_title')" />

    <div class="space-y-6">
        <ManageTwoFactor
            :can-manage-two-factor="canManageTwoFactor"
            :requires-confirmation="requiresConfirmation"
            :two-factor-enabled="twoFactorEnabled"
            hide-heading
        />

        <div class="flex justify-end">
            <Button as-child variant="outline">
                <Link href="/dashboard">{{ t('common.continue') }}</Link>
            </Button>
        </div>
    </div>
</template>
