<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import {
    annualSavingsEur,
    formatEur,
    monthlyYearTotalEur,
    type PlanPriceOption,
} from '@/lib/billingPricing';
import { store } from '@/routes/register';
import { login } from '@/routes';

defineOptions({
    layout: {
        titleKey: 'auth.register.title',
        descriptionKey: 'auth.register.description',
    },
});

type SubscriptionPlanOption = PlanPriceOption & {
    max_products: number | null;
};

const props = defineProps<{
    subscriptionPlans: SubscriptionPlanOption[];
    passwordRules: string;
    defaultLocale: string;
}>();

const { t, locale } = useTranslations();

const selectedPlan = ref('free');
const billingInterval = ref('month');

const isPaidPlan = computed(() => selectedPlan.value !== 'free');
const isYearly = computed(() => billingInterval.value === 'year');

const planPriceLabel = (plan: SubscriptionPlanOption): string => {
    if (plan.value === 'free') {
        return t('auth.register.price_free');
    }

    if (isYearly.value && plan.yearly_price_eur !== null) {
        return t('auth.register.price_year', {
            price: formatEur(plan.yearly_price_eur),
        });
    }

    return t('auth.register.price_month', {
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
        return t('auth.register.products_unlimited');
    }

    return t('auth.register.products_max', {
        max: String(plan.max_products),
    });
};
</script>

<template>
    <Head :title="t('auth.register.submit')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        autocomplete="off"
        data-1p-ignore
        data-lpignore="true"
        data-form-type="other"
    >
        <input type="hidden" name="locale" :value="locale || defaultLocale" />
        <input type="hidden" name="subscription_plan" :value="selectedPlan" />
        <input
            v-if="isPaidPlan"
            type="hidden"
            name="billing_interval"
            :value="billingInterval"
        />

        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label for="name">{{ t('auth.register.name') }}</Label>
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="t('auth.register.name')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('auth.register.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="username"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('auth.register.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :placeholder="t('auth.register.password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    t('auth.register.password_confirmation')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :placeholder="t('auth.register.password_confirmation')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <Label for="organization_name">{{
                    t('auth.register.organization_name')
                }}</Label>
                <Input
                    id="organization_name"
                    type="text"
                    name="organization_name"
                    required
                    :placeholder="t('auth.register.organization_name')"
                />
                <InputError :message="errors.organization_name" />
            </div>

            <div class="grid gap-2">
                <Label>{{ t('auth.register.plan') }}</Label>
                <div class="grid gap-2">
                    <button
                        v-for="plan in props.subscriptionPlans"
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
                <InputError :message="errors.subscription_plan" />
            </div>

            <div v-if="isPaidPlan" class="grid gap-2">
                <Label>{{ t('auth.register.billing_interval') }}</Label>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            billingInterval === 'month' ? 'default' : 'outline'
                        "
                        @click="billingInterval = 'month'"
                    >
                        {{ t('auth.register.interval_month') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            billingInterval === 'year' ? 'default' : 'outline'
                        "
                        @click="billingInterval = 'year'"
                    >
                        {{ t('auth.register.interval_year') }}
                    </Button>
                </div>
                <p
                    v-if="isYearly"
                    class="text-xs font-medium text-emerald-700 dark:text-emerald-400"
                >
                    {{ t('auth.register.annual_callout') }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ t('auth.register.paid_pending_note') }}
                </p>
                <InputError :message="errors.billing_interval" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.register.submit') }}
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            {{ t('auth.register.have_account') }}
            <TextLink :href="login()" class="underline underline-offset-4">
                {{ t('auth.register.login_link') }}
            </TextLink>
        </p>
    </Form>
</template>
