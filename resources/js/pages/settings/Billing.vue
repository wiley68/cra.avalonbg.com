<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Banknote } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editBilling } from '@/routes/settings/billing';
import { store as storeBankPayment } from '@/routes/settings/billing/bank-payment';

type OrganizationBilling = {
    id: number;
    name: string;
    subscription_plan: string;
    billing_status: string;
    billing_interval: string | null;
    billing_email: string | null;
    payment_method: string | null;
    billing_activated_at: string | null;
};

type PendingRequest = {
    id: number;
    subscription_plan: string;
    billing_interval: string;
    amount_eur: number;
    currency: string;
    payment_reference: string;
    status: string;
    created_at: string | null;
} | null;

type BankInstructions = {
    beneficiary: string;
    iban: string;
    bic: string;
    bank_name: string;
    reference_prefix: string;
};

const props = defineProps<{
    organization: OrganizationBilling;
    pendingRequest: PendingRequest;
    bankInstructions: BankInstructions;
    canRequestBankPayment: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'settings.nav.billing', href: editBilling() },
]);

const planLabel = computed(() =>
    t(`billing.plans.${props.organization.subscription_plan}`),
);

const statusLabel = computed(() =>
    t(`billing.status.${props.organization.billing_status}`),
);

const requestPayment = () => {
    router.post(storeBankPayment().url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('settings.nav.billing')" />

    <div class="space-y-6">
        <Heading
            :title="t('billing.title')"
            :description="t('billing.description')"
        />

        <div class="space-y-4 rounded-lg border p-5">
            <div class="grid gap-1">
                <p class="text-sm text-muted-foreground">
                    {{ t('billing.organization') }}
                </p>
                <p class="font-medium">{{ organization.name }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ t('billing.current_plan') }}
                    </p>
                    <p class="font-medium">{{ planLabel }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ t('billing.current_status') }}
                    </p>
                    <p class="font-medium">{{ statusLabel }}</p>
                </div>
            </div>
        </div>

        <div
            v-if="pendingRequest"
            class="space-y-4 rounded-lg border border-amber-500/40 bg-amber-500/5 p-5"
        >
            <h2 class="font-medium">{{ t('billing.pending_title') }}</h2>
            <p class="text-sm text-muted-foreground">
                {{ t('billing.pending_help') }}
            </p>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('billing.amount') }}
                    </dt>
                    <dd class="font-medium">
                        €{{ pendingRequest.amount_eur }}
                        {{ pendingRequest.currency }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('billing.payment_reference') }}
                    </dt>
                    <dd class="font-mono font-medium">
                        {{ pendingRequest.payment_reference }}
                    </dd>
                </div>
            </dl>

            <div class="space-y-2 rounded-md border bg-background p-4 text-sm">
                <p class="font-medium">{{ t('billing.bank_details') }}</p>
                <p v-if="bankInstructions.beneficiary">
                    {{ t('billing.beneficiary') }}:
                    {{ bankInstructions.beneficiary }}
                </p>
                <p v-if="bankInstructions.iban">
                    {{ t('billing.iban') }}:
                    <span class="font-mono">{{ bankInstructions.iban }}</span>
                </p>
                <p v-if="bankInstructions.bic">
                    {{ t('billing.bic') }}:
                    <span class="font-mono">{{ bankInstructions.bic }}</span>
                </p>
                <p v-if="bankInstructions.bank_name">
                    {{ t('billing.bank_name') }}:
                    {{ bankInstructions.bank_name }}
                </p>
                <p class="text-muted-foreground">
                    {{ t('billing.invoice_outside_note') }}
                </p>
            </div>
        </div>

        <div v-else-if="canRequestBankPayment" class="space-y-3">
            <p class="text-sm text-muted-foreground">
                {{ t('billing.request_help') }}
            </p>
            <Button type="button" @click="requestPayment">
                <Banknote class="h-4 w-4" />
                {{ t('billing.request_bank_payment') }}
            </Button>
        </div>

        <p
            v-else-if="organization.billing_status === 'active'"
            class="text-sm text-muted-foreground"
        >
            {{ t('billing.active_help') }}
        </p>
    </div>
</template>
