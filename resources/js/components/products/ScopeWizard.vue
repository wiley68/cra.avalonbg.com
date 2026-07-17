<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ClipboardList, Save } from '@lucide/vue';
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
import { useTranslations } from '@/composables/useTranslations';
import { preview as previewRoute } from '@/routes/products/scope-assessment';
import { store as storeAssessment } from '@/routes/products/scope-assessments';

export type ScopeAnswers = Record<string, string>;

export type ScopeAssessmentResult = {
    answers: ScopeAnswers;
    suggested_status: string;
    final_status: string;
    rationale: string;
};

const QUESTION_KEYS = [
    'product_kind',
    'commercial_activity',
    'network_or_device_link',
    'offered_standalone',
    'sold_under_own_brand',
    'remote_processing_required',
    'other_sector_regulation',
    'component_of_other_product',
    'free_open_source',
    'substantial_modification',
    'market_role',
    'offered_in_eu',
] as const;

const STEPS: (typeof QUESTION_KEYS)[number][][] = [
    ['product_kind', 'commercial_activity', 'network_or_device_link'],
    [
        'offered_standalone',
        'sold_under_own_brand',
        'remote_processing_required',
    ],
    [
        'other_sector_regulation',
        'component_of_other_product',
        'free_open_source',
    ],
    ['substantial_modification', 'market_role', 'offered_in_eu'],
];

const TRI_STATE = ['yes', 'no', 'unsure'] as const;
const MARKET_ROLES = [
    'manufacturer',
    'importer',
    'distributor',
    'unsure',
] as const;

const props = defineProps<{
    open: boolean;
    productId?: number | null;
    productTypes: string[];
    scopeStatuses: string[];
    initialAnswers?: ScopeAnswers | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirmed: [result: ScopeAssessmentResult];
}>();

const { t } = useTranslations();

const stepIndex = ref(0);
const previewError = ref('');
const evaluating = ref(false);
const suggestedStatus = ref('');
const answers = reactive<ScopeAnswers>({});
const stepErrors = reactive<Record<string, string>>({});

const form = useForm({
    answers: {} as ScopeAnswers,
    final_status: '',
    rationale: '',
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
        return true;
    }

    let ok = true;

    for (const key of currentQuestions.value) {
        if (!answers[key]) {
            stepErrors[key] = t('products.scope_wizard.errors.answer_required');
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
            previewError.value = t('products.scope_wizard.preview_error');

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
        previewError.value = t('products.scope_wizard.preview_error');

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
    const result: ScopeAssessmentResult = {
        answers: { ...answers },
        suggested_status: suggestedStatus.value,
        final_status: form.final_status,
        rationale: form.rationale,
    };

    if (props.productId) {
        form.transform(() => ({
            answers: result.answers,
            final_status: result.final_status,
            rationale: result.rationale,
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
                    <ClipboardList class="h-4 w-4" />
                    {{ t('products.scope_wizard.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('products.scope_wizard.subtitle') }}
                </DialogDescription>
            </DialogHeader>

            <p class="text-sm text-muted-foreground">
                {{
                    t('products.scope_wizard.step_of', {
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
                        :html-for="`scope_${key}`"
                        required
                        :help="t(`products.scope_wizard.questions.${key}.help`)"
                    >
                        {{ t(`products.scope_wizard.questions.${key}.label`) }}
                    </FieldLabel>

                    <select
                        v-if="key === 'product_kind'"
                        :id="`scope_${key}`"
                        v-model="answers[key]"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>
                            {{ t('common.select') }}
                        </option>
                        <option
                            v-for="value in productTypes"
                            :key="value"
                            :value="value"
                        >
                            {{ labelFor('types', value) }}
                        </option>
                    </select>

                    <select
                        v-else-if="key === 'market_role'"
                        :id="`scope_${key}`"
                        v-model="answers[key]"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>
                            {{ t('common.select') }}
                        </option>
                        <option
                            v-for="value in MARKET_ROLES"
                            :key="value"
                            :value="value"
                        >
                            {{
                                t(`products.scope_wizard.market_roles.${value}`)
                            }}
                        </option>
                    </select>

                    <select
                        v-else
                        :id="`scope_${key}`"
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
                    {{ t('products.scope_wizard.review_title') }}
                </h3>
                <div class="grid gap-2">
                    <p class="text-sm font-medium">
                        {{ t('products.scope_wizard.suggested_status') }}
                    </p>
                    <p class="text-sm">
                        {{ labelFor('scope', suggestedStatus) }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="scope_final_status"
                        required
                        :help="t('products.scope_wizard.override_hint')"
                    >
                        {{ t('products.scope_wizard.final_status') }}
                    </FieldLabel>
                    <select
                        id="scope_final_status"
                        v-model="form.final_status"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="value in scopeStatuses"
                            :key="value"
                            :value="value"
                        >
                            {{ labelFor('scope', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.final_status" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="scope_wizard_rationale"
                        :help="t('products.help.scope_rationale')"
                    >
                        {{ t('products.scope_wizard.rationale') }}
                    </FieldLabel>
                    <textarea
                        id="scope_wizard_rationale"
                        v-model="form.rationale"
                        rows="6"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.rationale" />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button type="button" variant="outline" @click="close">
                    {{ t('products.scope_wizard.cancel') }}
                </Button>
                <div class="flex gap-2">
                    <Button
                        v-if="stepIndex > 0"
                        type="button"
                        variant="outline"
                        @click="goBack"
                    >
                        {{ t('products.scope_wizard.back') }}
                    </Button>
                    <Button
                        v-if="!isReviewStep"
                        type="button"
                        :disabled="evaluating"
                        @click="goNext"
                    >
                        {{ t('products.scope_wizard.next') }}
                    </Button>
                    <Button
                        v-else
                        type="button"
                        :disabled="form.processing || !form.final_status"
                        @click="confirm"
                    >
                        <Save class="h-4 w-4" />
                        {{ t('products.scope_wizard.confirm') }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
