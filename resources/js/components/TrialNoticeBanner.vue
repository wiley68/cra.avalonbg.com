<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

type TrialNotice = {
    ends_at: string;
    days_remaining: number;
    promo_code: string | null;
    billing_href: string;
    can_convert: boolean;
};

const { t } = useTranslations();
const page = usePage();

const notice = computed(
    () => (page.props.trial_notice as TrialNotice | null) ?? null,
);

const body = computed(() => {
    if (!notice.value) {
        return '';
    }

    return t('billing.trial.banner_body', {
        days: String(notice.value.days_remaining),
    });
});
</script>

<template>
    <div
        v-if="notice"
        class="mb-4 flex flex-col gap-3 rounded-lg border border-sky-500/40 bg-sky-500/10 px-4 py-3 text-sm text-sky-950 sm:flex-row sm:items-center sm:justify-between dark:text-sky-50"
        role="status"
    >
        <div class="flex min-w-0 items-start gap-3">
            <Clock class="mt-0.5 h-4 w-4 shrink-0" />
            <div class="min-w-0 space-y-1">
                <p class="font-medium">{{ t('billing.trial.banner_title') }}</p>
                <p class="text-xs opacity-90">{{ body }}</p>
                <p v-if="notice.promo_code" class="text-xs opacity-80">
                    {{
                        t('billing.trial.promo_applied', {
                            code: notice.promo_code,
                        })
                    }}
                </p>
            </div>
        </div>
        <div v-if="notice.can_convert" class="flex shrink-0 flex-wrap gap-2">
            <Button as-child size="sm" variant="outline">
                <Link :href="notice.billing_href">
                    {{ t('billing.trial.open_billing') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
