<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Save, Send, Archive } from '@lucide/vue';
import { computed } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    approve as approvePolicy,
    edit as policiesEdit,
    index as policiesIndex,
    retire as retirePolicy,
    submitReview,
    update,
} from '@/routes/policies';
import { ref } from 'vue';

type PolicyDetail = {
    id: number;
    policy_type: string;
    title: string;
    status: string;
    version_label: string;
    body: string;
    notes: string | null;
    supersedes_id: number | null;
    supersedes_title: string | null;
    approved_at: string | null;
    approved_by_name: string | null;
    is_editable: boolean;
};

const props = defineProps<{
    policy: PolicyDetail;
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.policies', href: policiesIndex() },
    {
        title: props.policy.title,
        href: policiesEdit(props.policy.id),
    },
]);

const form = useForm({
    title: props.policy.title,
    version_label: props.policy.version_label,
    body: props.policy.body,
    notes: props.policy.notes ?? '',
});

const showRetireDialog = ref(false);

const typeLabel = computed(() => {
    const key = `policies.types.${props.policy.policy_type}`;
    const translated = t(key);

    return translated === key ? props.policy.policy_type : translated;
});

const statusLabel = computed(() => {
    const key = `policies.statuses.${props.policy.status}`;
    const translated = t(key);

    return translated === key ? props.policy.status : translated;
});

const canSubmit = computed(
    () => props.canManage && props.policy.status === 'draft',
);
const canApprove = computed(
    () => props.canManage && props.policy.status === 'under_review',
);
const canRetire = computed(
    () => props.canManage && props.policy.status === 'approved',
);

const submit = () => {
    form.put(update(props.policy.id).url);
};

const doSubmitReview = () => {
    router.post(
        submitReview(props.policy.id).url,
        {},
        { preserveScroll: true },
    );
};

const doApprove = () => {
    router.post(
        approvePolicy(props.policy.id).url,
        {},
        { preserveScroll: true },
    );
};

const doRetire = () => {
    showRetireDialog.value = false;
    router.post(
        retirePolicy(props.policy.id).url,
        {},
        { preserveScroll: true },
    );
};

const textareaClass =
    'border-input bg-background flex min-h-48 w-full rounded-md border px-3 py-2 font-mono text-sm disabled:opacity-60';
</script>

<template>
    <Head :title="t('policies.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('policies.edit_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ typeLabel }} · {{ statusLabel }} ·
                    {{ policy.version_label }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="policiesIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <div
            v-if="canManage"
            class="flex flex-wrap gap-2 rounded-lg border p-3"
        >
            <Button
                v-if="canSubmit"
                type="button"
                variant="outline"
                @click="doSubmitReview"
            >
                <Send class="h-4 w-4" />
                {{ t('policies.submit_review') }}
            </Button>
            <Button
                v-if="canApprove"
                type="button"
                variant="outline"
                @click="doApprove"
            >
                <CheckCircle2 class="h-4 w-4" />
                {{ t('policies.approve') }}
            </Button>
            <Button
                v-if="canRetire"
                type="button"
                variant="outline"
                @click="showRetireDialog = true"
            >
                <Archive class="h-4 w-4" />
                {{ t('policies.retire') }}
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <fieldset
                :disabled="!canManage || !policy.is_editable"
                class="space-y-5"
            >
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="title"
                        :help="t('policies.help.title')"
                        required
                    >
                        {{ t('policies.fields.title') }}
                    </FieldLabel>
                    <Input id="title" v-model="form.title" required />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="version_label"
                        :help="t('policies.help.version_label')"
                        required
                    >
                        {{ t('policies.fields.version_label') }}
                    </FieldLabel>
                    <Input
                        id="version_label"
                        v-model="form.version_label"
                        required
                    />
                    <InputError :message="form.errors.version_label" />
                </div>

                <div
                    v-if="policy.supersedes_title"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('policies.fields.supersedes') }}:
                    {{ policy.supersedes_title }}
                </div>

                <div
                    v-if="policy.approved_at"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('policies.fields.approved_at') }}:
                    {{ new Date(policy.approved_at).toLocaleString() }}
                    <span v-if="policy.approved_by_name">
                        ({{ policy.approved_by_name }})
                    </span>
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="body"
                        :help="t('policies.help.body')"
                        required
                    >
                        {{ t('policies.fields.body') }}
                    </FieldLabel>
                    <textarea
                        id="body"
                        v-model="form.body"
                        rows="16"
                        required
                        :class="textareaClass"
                    />
                    <InputError :message="form.errors.body" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">{{ t('policies.fields.notes') }}</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </fieldset>

            <div
                v-if="canManage && policy.is_editable"
                class="flex justify-end"
            >
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <AppAlertDialog
            v-model:open="showRetireDialog"
            :title="t('policies.confirm_retire_title')"
            :description="t('policies.confirm_retire')"
            @confirm="doRetire"
            @cancel="showRetireDialog = false"
        />
    </div>
</template>
