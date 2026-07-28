<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Banknote, CreditCard, Save } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BillingDocumentsPanel from '@/components/billing/BillingDocumentsPanel.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    annualSavingsEur,
    formatEur,
    monthlyYearTotalEur,
    type PlanPriceOption,
} from '@/lib/billingPricing';
import { changePlan, edit as editBilling } from '@/routes/settings/billing';
import { store as storeBankPayment } from '@/routes/settings/billing/bank-payment';
import { download as downloadDocument } from '@/routes/settings/billing/documents';
import {
    checkout as stripeCheckout,
    portal as stripePortal,
} from '@/routes/settings/billing/stripe';

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

type SubscriptionPlanOption = PlanPriceOption & {
    max_products: number | null;
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

type BillingDocumentItem = {
    id: number;
    type: string;
    title: string;
    source_filename: string;
    size_bytes: number;
    mime_type: string | null;
    sent_at: string | null;
    sent_to_email: string | null;
    notes: string | null;
    created_at: string | null;
};

const props = defineProps<{
    organization: OrganizationBilling;
    subscriptionPlans: SubscriptionPlanOption[];
    pendingRequest: PendingRequest;
    bankInstructions: BankInstructions;
    canRequestBankPayment: boolean;
    canCheckoutStripe: boolean;
    canManageStripe: boolean;
    canChangePlan: boolean;
    stripeConfigured: boolean;
    documents: BillingDocumentItem[];
    canManageDocuments: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'settings.nav.billing', href: editBilling() },
]);

const selectedPlan = ref(props.organization.subscription_plan);
const billingInterval = ref(props.organization.billing_interval ?? 'month');

watch(
    () => props.organization,
    (organization) => {
        selectedPlan.value = organization.subscription_plan;
        billingInterval.value = organization.billing_interval ?? 'month';
    },
);

const isPaidPlan = computed(() => selectedPlan.value !== 'free');
const isYearly = computed(() => billingInterval.value === 'year');

const planLabel = computed(() =>
    t(`billing.plans.${props.organization.subscription_plan}`),
);

const statusLabel = computed(() =>
    t(`billing.status.${props.organization.billing_status}`),
);

const intervalLabel = computed(() => {
    if (!props.organization.billing_interval) {
        return t('billing.interval.none');
    }

    return t(`billing.interval.${props.organization.billing_interval}`);
});

const paymentMethodLabel = computed(() => {
    if (!props.organization.payment_method) {
        return t('billing.payment_method.none');
    }

    return t(`billing.payment_method.${props.organization.payment_method}`);
});

const planDirty = computed(() => {
    const planChanged =
        selectedPlan.value !== props.organization.subscription_plan;
    const intervalChanged =
        isPaidPlan.value &&
        billingInterval.value !==
            (props.organization.billing_interval ?? 'month');

    return planChanged || intervalChanged;
});

const planPriceLabel = (plan: SubscriptionPlanOption): string => {
    if (plan.value === 'free') {
        return t('billing.change_plan.price_free');
    }

    if (isYearly.value && plan.yearly_price_eur !== null) {
        return t('billing.change_plan.price_year', {
            price: formatEur(plan.yearly_price_eur),
        });
    }

    return t('billing.change_plan.price_month', {
        price: formatEur(plan.monthly_price_eur),
    });
};

const planSecondaryPrice = (plan: SubscriptionPlanOption): string | null => {
    if (plan.value === 'free' || plan.yearly_price_eur === null) {
        return null;
    }

    if (isYearly.value) {
        const monthlyYear = monthlyYearTotalEur(plan);
        if (monthlyYear === null) {
            return null;
        }

        return t('billing.annual.vs_monthly_year', {
            price: formatEur(monthlyYear),
        });
    }

    return t('billing.annual.or_yearly', {
        price: formatEur(plan.yearly_price_eur),
    });
};

const planSavingsLabel = (plan: SubscriptionPlanOption): string | null => {
    if (!isYearly.value || plan.value === 'free') {
        return null;
    }

    const savings = annualSavingsEur(plan);
    if (savings === null) {
        return null;
    }

    return t('billing.annual.save_amount', {
        amount: formatEur(savings),
    });
};

const productLimitLabel = (plan: SubscriptionPlanOption): string => {
    if (plan.max_products === null) {
        return t('billing.change_plan.products_unlimited');
    }

    return t('billing.change_plan.products_max', {
        max: String(plan.max_products),
    });
};

const requestPayment = () => {
    router.post(storeBankPayment().url, {}, { preserveScroll: true });
};

const startStripeCheckout = () => {
    router.post(stripeCheckout().url);
};

const openStripePortal = () => {
    router.post(stripePortal().url);
};

const submitChangePlan = () => {
    router.post(
        changePlan().url,
        {
            subscription_plan: selectedPlan.value,
            billing_interval: isPaidPlan.value ? billingInterval.value : null,
        },
        { preserveScroll: true },
    );
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

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ t('billing.current_interval') }}
                    </p>
                    <p class="font-medium">{{ intervalLabel }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ t('billing.current_payment_method') }}
                    </p>
                    <p class="font-medium">{{ paymentMethodLabel }}</p>
                </div>
            </div>

            <div v-if="organization.billing_email" class="grid gap-1 text-sm">
                <p class="text-muted-foreground">
                    {{ t('billing.billing_email') }}
                </p>
                <p class="font-medium">{{ organization.billing_email }}</p>
            </div>
        </div>

        <div v-if="canManageStripe" class="space-y-3 rounded-lg border p-5">
            <h2 class="font-medium">{{ t('billing.stripe.manage_title') }}</h2>
            <p class="text-sm text-muted-foreground">
                {{ t('billing.stripe.manage_help') }}
            </p>
            <Button type="button" @click="openStripePortal">
                <CreditCard class="h-4 w-4" />
                {{ t('billing.stripe.manage') }}
            </Button>
        </div>

        <div v-else-if="canChangePlan" class="space-y-4 rounded-lg border p-5">
            <div class="space-y-1">
                <h2 class="font-medium">
                    {{ t('billing.change_plan.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('billing.change_plan.help') }}
                </p>
            </div>

            <div class="grid gap-2">
                <button
                    v-for="plan in subscriptionPlans"
                    :key="plan.value"
                    type="button"
                    class="rounded-md border px-3 py-2 text-left text-sm transition-colors"
                    :class="
                        selectedPlan === plan.value
                            ? 'border-primary bg-primary/5'
                            : 'hover:bg-muted/50'
                    "
                    @click="selectedPlan = plan.value"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{
                            t(`billing.plans.${plan.value}`)
                        }}</span>
                        <span class="font-medium text-foreground">{{
                            planPriceLabel(plan)
                        }}</span>
                    </div>
                    <p
                        v-if="planSecondaryPrice(plan)"
                        class="mt-0.5 text-xs text-muted-foreground"
                        :class="isYearly ? 'line-through' : ''"
                    >
                        {{ planSecondaryPrice(plan) }}
                    </p>
                    <p
                        v-if="planSavingsLabel(plan)"
                        class="mt-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                    >
                        {{ planSavingsLabel(plan) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ productLimitLabel(plan) }}
                    </p>
                </button>
            </div>

            <div v-if="isPaidPlan" class="space-y-2">
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            billingInterval === 'month' ? 'default' : 'outline'
                        "
                        @click="billingInterval = 'month'"
                    >
                        {{ t('billing.interval.month') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            billingInterval === 'year' ? 'default' : 'outline'
                        "
                        @click="billingInterval = 'year'"
                    >
                        {{ t('billing.interval.year') }}
                    </Button>
                </div>
                <p
                    v-if="isYearly"
                    class="text-xs font-medium text-emerald-700 dark:text-emerald-400"
                >
                    {{ t('billing.annual.callout') }}
                </p>
                <p v-else class="text-xs text-muted-foreground">
                    {{ t('billing.change_plan.annual_hint') }}
                </p>
            </div>

            <Button
                type="button"
                :disabled="!planDirty"
                @click="submitChangePlan"
            >
                <Save class="h-4 w-4" />
                {{ t('billing.change_plan.submit') }}
            </Button>
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

            <div
                v-if="canCheckoutStripe"
                class="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    {{ t('billing.stripe.alt_help') }}
                </p>
                <Button type="button" @click="startStripeCheckout">
                    <CreditCard class="h-4 w-4" />
                    {{ t('billing.stripe.checkout') }}
                </Button>
            </div>
        </div>

        <div
            v-else-if="canCheckoutStripe || canRequestBankPayment"
            class="space-y-4 rounded-lg border p-5"
        >
            <div v-if="canCheckoutStripe" class="space-y-3">
                <h2 class="font-medium">{{ t('billing.stripe.title') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('billing.stripe.help') }}
                </p>
                <Button type="button" @click="startStripeCheckout">
                    <CreditCard class="h-4 w-4" />
                    {{ t('billing.stripe.checkout') }}
                </Button>
            </div>

            <div
                v-if="canRequestBankPayment"
                class="space-y-3"
                :class="canCheckoutStripe ? 'border-t pt-4' : ''"
            >
                <p class="text-sm text-muted-foreground">
                    {{ t('billing.request_help') }}
                </p>
                <Button
                    type="button"
                    :variant="canCheckoutStripe ? 'outline' : 'default'"
                    @click="requestPayment"
                >
                    <Banknote class="h-4 w-4" />
                    {{ t('billing.request_bank_payment') }}
                </Button>
            </div>
        </div>

        <p
            v-else-if="
                organization.billing_status === 'active' && !canManageStripe
            "
            class="text-sm text-muted-foreground"
        >
            {{ t('billing.active_help') }}
        </p>

        <p
            v-else-if="
                organization.subscription_plan !== 'free' && !stripeConfigured
            "
            class="text-sm text-muted-foreground"
        >
            {{ t('billing.stripe.not_configured_hint') }}
        </p>

        <BillingDocumentsPanel
            :documents="documents"
            :can-manage="canManageDocuments"
            :download-url="(id) => downloadDocument(id).url"
        />
    </div>
</template>
