<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CreditCard } from '@lucide/vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useAiPlanGate } from '@/composables/useAiPlanGate';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editBilling } from '@/routes/settings/billing';

const { t } = useTranslations();
const { planLockedOpen, canManageBilling } = useAiPlanGate();
</script>

<template>
    <AlertDialog v-model:open="planLockedOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ t('assistant.plan_locked_title') }}
                </AlertDialogTitle>
                <AlertDialogDescription as-child>
                    <div class="space-y-2 text-sm text-muted-foreground">
                        <p>{{ t('assistant.plan_locked') }}</p>
                        <p>{{ t('assistant.plan_locked_help') }}</p>
                    </div>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>
                    {{ t('common.close') }}
                </AlertDialogCancel>
                <Button v-if="canManageBilling" as-child>
                    <Link :href="editBilling()">
                        <CreditCard class="h-4 w-4" />
                        {{ t('assistant.open_billing') }}
                    </Link>
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
