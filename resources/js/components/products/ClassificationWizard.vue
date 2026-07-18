<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Tags, Save } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { preview as previewRoute } from '@/routes/products/classification';
import { store as storeAssessment } from '@/routes/products/classifications';

export type ClassificationAnswers = Record<string, string>;

export type ClassificationAssessmentResult = {
    answers: ClassificationAnswers;
    suggested_status: string;
    final_status: string;
    rationale: string;
    regulatory_content_version: string;
    evidence_notes: string;
    next_review_at: string;
};

const QUESTION_KEYS = [
    'identity_access_security',
    'network_security',
    'endpoint_security',
    'browser_or_runtime',
    'operating_system',
    'hypervisor_containers',
    'pki_crypto',
    'critical_infrastructure',
    'sector_specific_regime',
    'explicitly_excluded',
] as const;

const STEPS: (typeof QUESTION_KEYS)[number][][] = [
    ['identity_access_security', 'network_security', 'endpoint_security'],
    ['browser_or_runtime', 'operating_system', 'hypervisor_containers'],
    ['pki_crypto', 'critical_infrastructure'],
    ['sector_specific_regime', 'explicitly_excluded'],
];

const TRI_STATE = ['yes', 'no', 'unsure'] as const;

const props = withDefaults(
    defineProps<{
        open: boolean;
        productId?: number | null;
        classificationStatuses: string[];
        initialAnswers?: ClassificationAnswers | null;
        initialRegulatoryContentVersion?: string | null;
        initialEvidenceNotes?: string | null;
        initialNextReviewAt?: string | null;
    }>(),
    {
        productId: null,
        initialAnswers: null,
        initialRegulatoryContentVersion: null,
        initialEvidenceNotes: null,
        initialNextReviewAt: null,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirmed: [result: ClassificationAssessmentResult];
}>();

const { t } = useTranslations();

const stepIndex = ref(0);
const previewError = ref('');
const evaluating = ref(false);
const suggestedStatus = ref('');
const answers = reactive<ClassificationAnswers>({});
const stepErrors = reactive<Record<string, string>>({});

const form = useForm({
    answers: {} as ClassificationAnswers,
    final_status: '',
    rationale: '',
    regulatory_content_version: '',
    evidence_notes: '',
    next_review_at: '',
});

const totalSteps = STEPS.length + 1;
const isReviewStep = computed(() => stepIndex.value === STEPS.length);
const currentQuestions = computed(() =>
    isReviewStep.value ? [] : STEPS[stepIndex.value],
);

const resetState = () => {
    stepIndex.value = 0;
    previewError.value = '';
    suggestedStatus.value = '';
    Object.keys(answers).forEach((key) => delete answers[key]);
    Object.keys(stepErrors).forEach((key) => delete stepErrors[key]);

    const seed = props.initialAnswers ?? {};

    for (const key of QUESTION_KEYS) {
        answers[key] = seed[key] ?? '';
    }

    form.final_status = '';
    form.rationale = '';
    form.regulatory_content_version =
        props.initialRegulatoryContentVersion ??
        t('products.classification_wizard.default_regulatory_version');
    form.evidence_notes = props.initialEvidenceNotes ?? '';
    form.next_review_at = props.initialNextReviewAt ?? '';
    form.clearErrors();
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            resetState();
        }
    },
);

const labelFor = (group: string, value: string): string => {
    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const close = () => {
    emit('update:open', false);
};

const validateCurrentStep = (): boolean => {
    Object.keys(stepErrors).forEach((key) => delete stepErrors[key]);

    if (isReviewStep.value) {
        let ok = true;

        if (!form.regulatory_content_version.trim()) {
            form.setError(
                'regulatory_content_version',
                t('products.classification_wizard.errors.regulatory_required'),
            );
            ok = false;
        }

        if (!form.final_status) {
            form.setError(
                'final_status',
                t('products.classification_wizard.errors.answer_required'),
            );
            ok = false;
        }

        return ok;
    }

    let ok = true;

    for (const key of currentQuestions.value) {
        if (!answers[key]) {
            stepErrors[key] = t(
                'products.classification_wizard.errors.answer_required',
            );
            ok = false;
        }
    }

    return ok;
};

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const loadPreview = async (): Promise<boolean> => {
    evaluating.value = true;
    previewError.value = '';

    try {
        const response = await fetch(previewRoute().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ answers: { ...answers } }),
        });

        if (!response.ok) {
            previewError.value = t(
                'products.classification_wizard.preview_error',
            );

            return false;
        }

        const payload = (await response.json()) as {
            suggested_status: string;
            rationale: string;
        };

        suggestedStatus.value = payload.suggested_status;
        form.final_status = payload.suggested_status;
        form.rationale = payload.rationale;

        return true;
    } catch {
        previewError.value = t('products.classification_wizard.preview_error');

        return false;
    } finally {
        evaluating.value = false;
    }
};

const goNext = async () => {
    if (!validateCurrentStep()) {
        return;
    }

    if (stepIndex.value === STEPS.length - 1) {
        const ok = await loadPreview();

        if (!ok) {
            return;
        }
    }

    if (stepIndex.value < STEPS.length) {
        stepIndex.value += 1;
    }
};

const goBack = () => {
    if (stepIndex.value > 0) {
        stepIndex.value -= 1;
        previewError.value = '';
    }
};

const confirm = () => {
    if (!validateCurrentStep()) {
        return;
    }

    const result: ClassificationAssessmentResult = {
        answers: { ...answers },
        suggested_status: suggestedStatus.value,
        final_status: form.final_status,
        rationale: form.rationale,
        regulatory_content_version: form.regulatory_content_version.trim(),
        evidence_notes: form.evidence_notes,
        next_review_at: form.next_review_at,
    };

    if (props.productId) {
        form.transform(() => ({
            answers: result.answers,
            final_status: result.final_status,
            rationale: result.rationale,
            regulatory_content_version: result.regulatory_content_version,
            evidence_notes: result.evidence_notes || null,
            next_review_at: result.next_review_at || null,
        })).post(storeAssessment(props.productId).url, {
            preserveScroll: true,
            onSuccess: () => {
                emit('confirmed', result);
                close();
            },
        });

        return;
    }

    emit('confirmed', result);
    close();
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Tags class="h-4 w-4" />
                    {{ t('products.classification_wizard.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('products.classification_wizard.subtitle') }}
                </DialogDescription>
            </DialogHeader>

            <p class="text-sm text-muted-foreground">
                {{
                    t('products.classification_wizard.step_of', {
                        current: String(stepIndex + 1),
                        total: String(totalSteps),
                    })
                }}
            </p>

            <div v-if="!isReviewStep" class="grid gap-4">
                <div
                    v-for="key in currentQuestions"
                    :key="key"
                    class="grid gap-2"
                >
                    <FieldLabel
                        :html-for="`classification_${key}`"
                        required
                        :help="
                            t(
                                `products.classification_wizard.questions.${key}.help`,
                            )
                        "
                    >
                        {{
                            t(
                                `products.classification_wizard.questions.${key}.label`,
                            )
                        }}
                    </FieldLabel>

                    <select
                        :id="`classification_${key}`"
                        v-model="answers[key]"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>
                            {{ t('common.select') }}
                        </option>
                        <option
                            v-for="value in TRI_STATE"
                            :key="value"
                            :value="value"
                        >
                            {{ t(`products.scope_wizard.tri_state.${value}`) }}
                        </option>
                    </select>

                    <InputError :message="stepErrors[key]" />
                </div>
                <p v-if="previewError" class="text-sm text-destructive">
                    {{ previewError }}
                </p>
            </div>

            <div v-else class="grid gap-4">
                <h3 class="text-sm font-semibold">
                    {{ t('products.classification_wizard.review_title') }}
                </h3>
                <div class="grid gap-2">
                    <p class="text-sm font-medium">
                        {{
                            t('products.classification_wizard.suggested_status')
                        }}
                    </p>
                    <p class="text-sm">
                        {{ labelFor('classification', suggestedStatus) }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="classification_final_status"
                        required
                        :help="
                            t('products.classification_wizard.override_hint')
                        "
                    >
                        {{ t('products.classification_wizard.final_status') }}
                    </FieldLabel>
                    <select
                        id="classification_final_status"
                        v-model="form.final_status"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="value in classificationStatuses"
                            :key="value"
                            :value="value"
                        >
                            {{ labelFor('classification', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.final_status" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="classification_regulatory_version"
                        required
                        :help="
                            t(
                                'products.classification_wizard.help.regulatory_content_version',
                            )
                        "
                    >
                        {{
                            t(
                                'products.classification_wizard.regulatory_content_version',
                            )
                        }}
                    </FieldLabel>
                    <Input
                        id="classification_regulatory_version"
                        v-model="form.regulatory_content_version"
                    />
                    <InputError
                        :message="form.errors.regulatory_content_version"
                    />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="classification_evidence_notes"
                        :help="
                            t(
                                'products.classification_wizard.help.evidence_notes',
                            )
                        "
                    >
                        {{ t('products.classification_wizard.evidence_notes') }}
                    </FieldLabel>
                    <textarea
                        id="classification_evidence_notes"
                        v-model="form.evidence_notes"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.evidence_notes" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="classification_wizard_next_review"
                        :help="t('products.help.next_review')"
                    >
                        {{ t('products.fields.next_review') }}
                    </FieldLabel>
                    <Input
                        id="classification_wizard_next_review"
                        v-model="form.next_review_at"
                        type="date"
                    />
                    <InputError :message="form.errors.next_review_at" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="classification_wizard_rationale"
                        :help="t('products.help.classification_rationale')"
                    >
                        {{ t('products.classification_wizard.rationale') }}
                    </FieldLabel>
                    <textarea
                        id="classification_wizard_rationale"
                        v-model="form.rationale"
                        rows="5"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.rationale" />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button type="button" variant="outline" @click="close">
                    {{ t('products.classification_wizard.cancel') }}
                </Button>
                <div class="flex gap-2">
                    <Button
                        v-if="stepIndex > 0"
                        type="button"
                        variant="outline"
                        @click="goBack"
                    >
                        {{ t('products.classification_wizard.back') }}
                    </Button>
                    <Button
                        v-if="!isReviewStep"
                        type="button"
                        :disabled="evaluating"
                        @click="goNext"
                    >
                        {{ t('products.classification_wizard.next') }}
                    </Button>
                    <Button
                        v-else
                        type="button"
                        :disabled="form.processing || !form.final_status"
                        @click="confirm"
                    >
                        <Save class="h-4 w-4" />
                        {{ t('products.classification_wizard.confirm') }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
