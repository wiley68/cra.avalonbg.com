<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { portal as stripePortal } from '@/routes/settings/billing/stripe';

type BillingNotice = {
    status: string;
    in_grace: boolean;
    grace_ends_at: string | null;
    read_only_hint: boolean;
    billing_href: string;
    can_manage_stripe: boolean;
};

const { t } = useTranslations();
const page = usePage();

const notice = computed(
    () => (page.props.billing_notice as BillingNotice | null) ?? null,
);

const title = computed(() => {
    if (!notice.value) {
        return '';
    }

    if (notice.value.status === 'cancelled') {
        return t('billing.dunning.cancelled_title');
    }

    return notice.value.in_grace
        ? t('billing.dunning.past_due_grace_title')
        : t('billing.dunning.past_due_readonly_title');
});

const body = computed(() => {
    if (!notice.value) {
        return '';
    }

    if (notice.value.status === 'cancelled') {
        return t('billing.dunning.cancelled_body');
    }

    return notice.value.in_grace
        ? t('billing.dunning.past_due_grace_body')
        : t('billing.dunning.past_due_readonly_body');
});

const openStripePortal = (): void => {
    router.post(stripePortal().url);
};
</script>

<template>
    <div
        v-if="notice"
        class="mb-4 flex flex-col gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between dark:text-amber-50"
        role="status"
    >
        <div class="flex min-w-0 items-start gap-3">
            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
            <div class="min-w-0 space-y-1">
                <p class="font-medium">{{ title }}</p>
                <p class="text-xs opacity-90">{{ body }}</p>
                <p class="text-xs opacity-80">
                    {{ t('billing.dunning.no_delete_note') }}
                </p>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <Button
                v-if="notice.can_manage_stripe"
                type="button"
                size="sm"
                @click="openStripePortal"
            >
                {{ t('billing.stripe.manage') }}
            </Button>
            <Button as-child size="sm" variant="outline">
                <Link :href="notice.billing_href">
                    {{ t('billing.dunning.open_billing') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
