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
</script>

<template>
    <Head title="Set up 2FA" />

    <div class="mx-auto flex min-h-screen w-full max-w-2xl items-center px-6">
        <div class="w-full space-y-6 rounded-lg border bg-card p-6">
            <div class="space-y-2">
                <h1 class="text-xl font-semibold">Enable two-factor authentication</h1>
                <p class="text-sm text-muted-foreground">
                    Two-factor authentication is required before you can use the workspace.
                </p>
            </div>

            <ManageTwoFactor
                :can-manage-two-factor="canManageTwoFactor"
                :requires-confirmation="requiresConfirmation"
                :two-factor-enabled="twoFactorEnabled"
            />

            <div class="flex justify-end">
                <Button as-child variant="outline">
                    <Link href="/dashboard">Continue</Link>
                </Button>
            </div>
        </div>
    </div>
</template>

