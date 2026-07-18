<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, FileDown, Save, Send, Siren, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    approve as approveReport,
    escalate as escalateReport,
    exportMethod as reportingExport,
    markSubmitted,
    reject as rejectReport,
    submitApproval,
    update as updateDraft,
} from '@/routes/products/vulnerabilities/reporting';
import { index as productVulnerabilitiesIndex } from '@/routes/products/vulnerabilities';

type ProductSummary = { id: number; name: string; slug: string };

type Submission = {
    id: number;
    type: string;
    status: string;
    summary: string | null;
    impact: string | null;
    affected_versions_text: string | null;
    workaround: string | null;
    corrective_action: string | null;
    contact_name: string | null;
    contact_email: string | null;
    notes: string | null;
    submitted_at: string | null;
    submission_channel: string | null;
    submission_reference: string | null;
    approval_comment: string | null;
    evidence_id: number | null;
};

type Milestone = {
    type: string;
    due_at: string | null;
    status: string;
    overdue: boolean;
    remaining_seconds: number | null;
    applicable: boolean;
    summary: string;
    submission: Submission | null;
};

type Wizard = {
    vulnerability: {
        id: number;
        title: string;
        cve_id: string | null;
        awareness_at: string | null;
        exploitation_status: string;
        owner_name: string | null;
        substitute_owner_name: string | null;
        corrective_measure_available_at: string | null;
    };
    milestones: Milestone[];
    overdue_count: number;
    has_overdue: boolean;
    channels: string[];
    types: string[];
};

const props = defineProps<{
    product: ProductSummary;
    wizard: Wizard;
    canManage: boolean;
    canApprove: boolean;
}>();

const { t } = useTranslations();

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const routeArgs = computed(() => ({
    product: props.product.id,
    vulnerability: props.wizard.vulnerability.id,
}));

const activeType = ref(
    props.wizard.milestones.find((m) => m.applicable)?.type ??
        props.wizard.types[0] ??
        'early_warning',
);

const activeMilestone = computed(
    () =>
        props.wizard.milestones.find((m) => m.type === activeType.value) ??
        props.wizard.milestones[0],
);

const draftForm = useForm({
    type: activeType.value,
    summary: '',
    impact: '',
    affected_versions_text: '',
    workaround: '',
    corrective_action: '',
    contact_name: '',
    contact_email: '',
    notes: '',
});

const submitForm = useForm({
    type: activeType.value,
    submission_channel: props.wizard.channels[0] ?? 'email',
    submission_reference: '',
    notes: '',
});

const approvalComment = ref('');

const syncFormsFromMilestone = (): void => {
    const submission = activeMilestone.value?.submission;
    draftForm.type = activeType.value;
    draftForm.summary = submission?.summary ?? '';
    draftForm.impact = submission?.impact ?? '';
    draftForm.affected_versions_text = submission?.affected_versions_text ?? '';
    draftForm.workaround = submission?.workaround ?? '';
    draftForm.corrective_action = submission?.corrective_action ?? '';
    draftForm.contact_name = submission?.contact_name ?? '';
    draftForm.contact_email = submission?.contact_email ?? '';
    draftForm.notes = submission?.notes ?? '';
    submitForm.type = activeType.value;
    submitForm.notes = submission?.notes ?? '';
    approvalComment.value = submission?.approval_comment ?? '';
};

watch(activeType, syncFormsFromMilestone, { immediate: true });

const locked = computed(
    () =>
        activeMilestone.value?.submission?.status === 'submitted' ||
        activeMilestone.value?.submission?.status === 'pending_approval',
);

const formatCountdown = (seconds: number | null): string => {
    if (seconds === null) {
        return '—';
    }

    const abs = Math.abs(seconds);
    const hours = Math.floor(abs / 3600);
    const minutes = Math.floor((abs % 3600) / 60);

    if (seconds < 0) {
        return t('products.vulnerabilities.reporting.overdue_by', {
            hours: String(hours),
            minutes: String(minutes),
        });
    }

    return t('products.vulnerabilities.reporting.remaining', {
        hours: String(hours),
        minutes: String(minutes),
    });
};

const milestoneStatusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'submitted':
            return 'default';
        case 'overdue':
            return 'destructive';
        case 'warn':
            return 'secondary';
        default:
            return 'outline';
    }
};

const saveDraft = (): void => {
    draftForm.type = activeType.value;
    draftForm.put(updateDraft(routeArgs.value).url, {
        preserveScroll: true,
    });
};

const postType = (url: string): void => {
    router.post(
        url,
        { type: activeType.value, comment: approvalComment.value || null },
        { preserveScroll: true },
    );
};

const saveSubmitted = (): void => {
    submitForm.type = activeType.value;
    submitForm.post(markSubmitted(routeArgs.value).url, {
        preserveScroll: true,
    });
};

const escalate = (): void => {
    router.post(
        escalateReport(routeArgs.value).url,
        {},
        { preserveScroll: true },
    );
};

const exportUrl = computed(() => reportingExport(routeArgs.value).url);
</script>

<template>
    <Head :title="t('products.vulnerabilities.reporting.title')" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }} ·
                    {{ props.wizard.vulnerability.title }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.vulnerabilities.reporting.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.vulnerabilities.reporting.subtitle') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="productVulnerabilitiesIndex(props.product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <a
                        :href="exportUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <FileDown class="h-4 w-4" />
                        {{ t('products.vulnerabilities.reporting.export') }}
                    </a>
                </Button>
            </div>
        </div>

        <div
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            {{ t('products.vulnerabilities.reporting.disclaimer') }}
        </div>

        <div
            v-if="props.wizard.has_overdue && props.canManage"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3"
        >
            <p class="text-sm">
                {{ t('products.vulnerabilities.reporting.escalation_banner') }}
            </p>
            <Button type="button" variant="destructive" @click="escalate">
                <Siren class="h-4 w-4" />
                {{ t('products.vulnerabilities.reporting.escalate') }}
            </Button>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <article
                v-for="milestone in props.wizard.milestones"
                :key="milestone.type"
                class="cursor-pointer space-y-2 rounded-lg border p-4 transition-colors"
                :class="
                    activeType === milestone.type
                        ? 'border-primary ring-1 ring-primary/30'
                        : ''
                "
                @click="activeType = milestone.type"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-medium">
                        {{
                            t(
                                `products.vulnerabilities.reporting.types.${milestone.type}`,
                            )
                        }}
                    </h2>
                    <Badge :variant="milestoneStatusVariant(milestone.status)">
                        {{
                            t(
                                `products.vulnerabilities.reporting.milestone_status.${milestone.status}`,
                            )
                        }}
                    </Badge>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{
                        t(
                            `products.vulnerabilities.reporting.summaries.${milestone.summary}`,
                        )
                    }}
                </p>
                <p class="text-sm font-medium">
                    {{ formatCountdown(milestone.remaining_seconds) }}
                </p>
                <p
                    v-if="milestone.due_at"
                    class="text-xs text-muted-foreground"
                >
                    {{ t('products.vulnerabilities.reporting.due_at') }}:
                    {{ new Date(milestone.due_at).toLocaleString() }}
                </p>
            </article>
        </div>

        <section class="space-y-4 rounded-lg border p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">
                    {{
                        t(
                            `products.vulnerabilities.reporting.types.${activeType}`,
                        )
                    }}
                </h2>
                <Badge v-if="activeMilestone?.submission" variant="outline">
                    {{
                        t(
                            `products.vulnerabilities.reporting.submission_status.${activeMilestone.submission.status}`,
                        )
                    }}
                </Badge>
            </div>

            <div
                v-if="!activeMilestone?.applicable"
                class="text-sm text-muted-foreground"
            >
                {{
                    t(
                        'products.vulnerabilities.reporting.summaries.not_applicable',
                    )
                }}
            </div>

            <template v-else>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <FieldLabel
                            html-for="summary"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.summary',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.summary',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="summary"
                            v-model="draftForm.summary"
                            :class="textareaClass"
                            :disabled="!canManage || locked"
                        />
                        <InputError :message="draftForm.errors.summary" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <FieldLabel
                            html-for="impact"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.impact',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.impact',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="impact"
                            v-model="draftForm.impact"
                            :class="textareaClass"
                            :disabled="!canManage || locked"
                        />
                        <InputError :message="draftForm.errors.impact" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <FieldLabel
                            html-for="affected_versions_text"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.affected_versions',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.affected_versions',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="affected_versions_text"
                            v-model="draftForm.affected_versions_text"
                            :class="textareaClass"
                            :disabled="!canManage || locked"
                        />
                        <InputError
                            :message="draftForm.errors.affected_versions_text"
                        />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="workaround"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.workaround',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.workaround',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="workaround"
                            v-model="draftForm.workaround"
                            :class="textareaClass"
                            :disabled="!canManage || locked"
                        />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="corrective_action"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.corrective_action',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.corrective_action',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="corrective_action"
                            v-model="draftForm.corrective_action"
                            :class="textareaClass"
                            :disabled="!canManage || locked"
                        />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="contact_name"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.contact_name',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.contact_name',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            id="contact_name"
                            v-model="draftForm.contact_name"
                            :disabled="!canManage || locked"
                        />
                        <InputError :message="draftForm.errors.contact_name" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="contact_email"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.contact_email',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.contact_email',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            id="contact_email"
                            v-model="draftForm.contact_email"
                            type="email"
                            :disabled="!canManage || locked"
                        />
                        <InputError :message="draftForm.errors.contact_email" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <FieldLabel
                            html-for="notes"
                            :help="
                                t(
                                    'products.vulnerabilities.reporting.help.notes',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.reporting.fields.notes',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="notes"
                            v-model="draftForm.notes"
                            :class="textareaClass"
                            :disabled="!canManage || locked"
                        />
                    </div>
                </div>

                <div
                    v-if="canManage"
                    class="flex flex-wrap items-center gap-2 border-t pt-4"
                >
                    <Button
                        type="button"
                        :disabled="locked || draftForm.processing"
                        @click="saveDraft"
                    >
                        <Save class="h-4 w-4" />
                        {{ t('common.save') }}
                    </Button>
                    <Button
                        v-if="
                            activeMilestone?.submission &&
                            ['draft', 'rejected', 'approved'].includes(
                                activeMilestone.submission.status,
                            )
                        "
                        type="button"
                        variant="outline"
                        @click="postType(submitApproval(routeArgs).url)"
                    >
                        <Send class="h-4 w-4" />
                        {{
                            t(
                                'products.vulnerabilities.reporting.submit_approval',
                            )
                        }}
                    </Button>
                </div>

                <div
                    v-if="
                        canApprove &&
                        activeMilestone?.submission?.status ===
                            'pending_approval'
                    "
                    class="space-y-3 border-t pt-4"
                >
                    <FieldLabel
                        html-for="approval_comment"
                        :help="
                            t(
                                'products.vulnerabilities.reporting.help.approval_comment',
                            )
                        "
                    >
                        {{
                            t(
                                'products.vulnerabilities.reporting.fields.approval_comment',
                            )
                        }}
                    </FieldLabel>
                    <textarea
                        id="approval_comment"
                        v-model="approvalComment"
                        :class="textareaClass"
                    />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            @click="postType(approveReport(routeArgs).url)"
                        >
                            <Check class="h-4 w-4" />
                            {{
                                t('products.vulnerabilities.reporting.approve')
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            @click="postType(rejectReport(routeArgs).url)"
                        >
                            <X class="h-4 w-4" />
                            {{ t('products.vulnerabilities.reporting.reject') }}
                        </Button>
                    </div>
                </div>

                <div
                    v-if="
                        canManage &&
                        activeMilestone?.submission?.status === 'approved'
                    "
                    class="space-y-3 border-t pt-4"
                >
                    <h3 class="font-medium">
                        {{
                            t(
                                'products.vulnerabilities.reporting.mark_submitted_title',
                            )
                        }}
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <FieldLabel
                                html-for="submission_channel"
                                :help="
                                    t(
                                        'products.vulnerabilities.reporting.help.channel',
                                    )
                                "
                            >
                                {{
                                    t(
                                        'products.vulnerabilities.reporting.fields.channel',
                                    )
                                }}
                            </FieldLabel>
                            <select
                                id="submission_channel"
                                v-model="submitForm.submission_channel"
                                :class="selectClass"
                            >
                                <option
                                    v-for="channel in wizard.channels"
                                    :key="channel"
                                    :value="channel"
                                >
                                    {{
                                        t(
                                            `products.vulnerabilities.reporting.channels.${channel}`,
                                        )
                                    }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <FieldLabel
                                html-for="submission_reference"
                                :help="
                                    t(
                                        'products.vulnerabilities.reporting.help.reference',
                                    )
                                "
                            >
                                {{
                                    t(
                                        'products.vulnerabilities.reporting.fields.reference',
                                    )
                                }}
                            </FieldLabel>
                            <Input
                                id="submission_reference"
                                v-model="submitForm.submission_reference"
                            />
                        </div>
                    </div>
                    <Button
                        type="button"
                        :disabled="submitForm.processing"
                        @click="saveSubmitted"
                    >
                        <Check class="h-4 w-4" />
                        {{
                            t(
                                'products.vulnerabilities.reporting.mark_submitted',
                            )
                        }}
                    </Button>
                </div>
            </template>
        </section>
    </div>
</template>
