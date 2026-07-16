<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import { Button } from '@/components/ui/button';

type Props = {
    canManageTwoFactor: boolean;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
};

defineProps<Props>();

defineOptions({
    layout: {
        title: 'Enable two-factor authentication',
        description:
            'Two-factor authentication is required before you can use the workspace.',
    },
});
</script>

<template>
    <Head title="Set up 2FA" />

    <div class="space-y-6">
        <ManageTwoFactor
            :can-manage-two-factor="canManageTwoFactor"
            :requires-confirmation="requiresConfirmation"
            :two-factor-enabled="twoFactorEnabled"
            hide-heading
        />

        <div class="flex justify-end">
            <Button as-child variant="outline">
                <Link href="/dashboard">Continue</Link>
            </Button>
        </div>
    </div>
</template>
