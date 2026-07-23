<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    edit as productSdlEdit,
    index as productSdlIndex,
    update,
} from '@/routes/products/sdl';
import { update as updateSdlStage } from '@/routes/products/sdl/stages';
import { edit as editProduct, index as productsIndex } from '@/routes/products';

type Member = { id: number; name: string; email: string };
type VersionOption = { id: number; version_number: string };
type EvidenceOption = { id: number; title: string };
type ProductSummary = { id: number; name: string; slug: string };
type StageEntry = {
    id: number | null;
    stage: string;
    status: string;
    completed_at: string | null;
    completed_by: number | null;
    completed_by_name: string | null;
    notes: string | null;
    evidence_ids: number[];
};
type StageDraft = {
    status: string;
    notes: string;
    evidence_ids: number[];
};
type SdlRunDetail = {
    id: number;
    title: string;
    status: string;
    current_stage: string;
    product_version_id: number | null;
    owner_user_id: number | null;
    notes: string | null;
    approved_at: string | null;
    approved_by_name: string | null;
    is_terminal: boolean;
    is_approved: boolean;
    evidence_ids: number[];
    stage_entries: StageEntry[];
};

const props = defineProps<{
    product: ProductSummary;
    run: SdlRunDetail;
    members: Member[];
    versions: VersionOption[];
    evidence: EvidenceOption[];
    canManage: boolean;
    options: {
        statuses: string[];
        stages: string[];
        stage_statuses: string[];
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.sdl.index_title',
        href: productSdlIndex(props.product.id),
    },
    {
        titleKey: 'products.sdl.edit_title',
        href: productSdlEdit({
            product: props.product.id,
            sdlRun: props.run.id,
        }),
    },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const form = useForm({
    title: props.run.title,
    status: props.run.status,
    current_stage: props.run.current_stage,
    product_version_id: (props.run.product_version_id ?? '') as number | '',
    owner_user_id: (props.run.owner_user_id ?? '') as number | '',
    notes: props.run.notes ?? '',
    evidence_ids: [...props.run.evidence_ids],
});

const buildStageDrafts = (entries: StageEntry[]): Record<string, StageDraft> =>
    Object.fromEntries(
        entries.map((entry) => [
            entry.stage,
            {
                status: entry.status,
                notes: entry.notes ?? '',
                evidence_ids: [...(entry.evidence_ids ?? [])],
            },
        ]),
    );

const stageDrafts = reactive<Record<string, StageDraft>>(
    buildStageDrafts(props.run.stage_entries),
);
const savingStage = ref<string | null>(null);
const stageErrors = reactive<Record<string, Record<string, string>>>({});

watch(
    () => props.run.stage_entries,
    (entries) => {
        Object.assign(stageDrafts, buildStageDrafts(entries));
    },
    { deep: true },
);

watch(
    () => props.run.evidence_ids,
    (ids) => {
        form.evidence_ids = [...ids];
    },
);

const submit = () => {
    if (!props.canManage) {
        return;
    }

    form.transform((data) => ({
        ...data,
        product_version_id: data.product_version_id || null,
        owner_user_id: data.owner_user_id || null,
    })).put(
        update({
            product: props.product.id,
            sdlRun: props.run.id,
        }).url,
    );
};

const saveStage = (stage: string) => {
    if (!props.canManage) {
        return;
    }

    const draft = stageDrafts[stage];

    if (!draft) {
        return;
    }

    savingStage.value = stage;
    delete stageErrors[stage];

    router.put(
        updateSdlStage({
            product: props.product.id,
            sdlRun: props.run.id,
            stage,
        }).url,
        {
            status: draft.status,
            notes: draft.notes || null,
            evidence_ids: draft.evidence_ids,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                stageErrors[stage] = errors as Record<string, string>;
            },
            onFinish: () => {
                savingStage.value = null;
            },
        },
    );
};

const toggleRunEvidence = (id: number, checked: boolean) => {
    if (checked) {
        if (!form.evidence_ids.includes(id)) {
            form.evidence_ids.push(id);
        }

        return;
    }

    form.evidence_ids = form.evidence_ids.filter((value) => value !== id);
};

const toggleStageEvidence = (stage: string, id: number, checked: boolean) => {
    const draft = stageDrafts[stage];

    if (!draft) {
        return;
    }

    if (checked) {
        if (!draft.evidence_ids.includes(id)) {
            draft.evidence_ids.push(id);
        }

        return;
    }

    draft.evidence_ids = draft.evidence_ids.filter((value) => value !== id);
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.sdl.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
};

const stageCompletedLabel = (entry: StageEntry): string => {
    if (!entry.completed_at) {
        return '';
    }

    let label = t('products.sdl.stage_completed_meta', {
        when: formatDateTime(entry.completed_at),
    });

    if (entry.completed_by_name) {
        label += ` ${t('products.sdl.stage_completed_by', {
            name: entry.completed_by_name,
        })}`;
    }

    return label;
};
</script>

<template>
    <Head :title="t('products.sdl.edit_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.sdl.edit_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productSdlIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <fieldset class="space-y-4" :disabled="!props.canManage">
                <div class="space-y-2">
                    <FieldLabel
                        html-for="title"
                        required
                        :help="t('products.sdl.help.title')"
                    >
                        {{ t('products.sdl.fields.title') }}
                    </FieldLabel>
                    <Input
                        id="title"
                        v-model="form.title"
                        required
                        maxlength="255"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <FieldLabel
                            html-for="status"
                            required
                            :help="t('products.sdl.help.status')"
                        >
                            {{ t('products.sdl.fields.status') }}
                        </FieldLabel>
                        <select
                            id="status"
                            v-model="form.status"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="status in props.options.statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ enumLabel('statuses', status) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="space-y-2">
                        <FieldLabel
                            html-for="current_stage"
                            required
                            :help="t('products.sdl.help.current_stage')"
                        >
                            {{ t('products.sdl.fields.current_stage') }}
                        </FieldLabel>
                        <select
                            id="current_stage"
                            v-model="form.current_stage"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="stage in props.options.stages"
                                :key="stage"
                                :value="stage"
                            >
                                {{ enumLabel('stages', stage) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.current_stage" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <FieldLabel
                            html-for="product_version_id"
                            :help="t('products.sdl.help.product_version')"
                        >
                            {{ t('products.sdl.fields.product_version') }}
                        </FieldLabel>
                        <select
                            id="product_version_id"
                            v-model="form.product_version_id"
                            :class="selectClass"
                        >
                            <option value="">
                                {{ t('products.sdl.version_none') }}
                            </option>
                            <option
                                v-for="version in props.versions"
                                :key="version.id"
                                :value="version.id"
                            >
                                {{ version.version_number }}
                            </option>
                        </select>
                        <InputError :message="form.errors.product_version_id" />
                    </div>

                    <div class="space-y-2">
                        <FieldLabel
                            html-for="owner_user_id"
                            :help="t('products.sdl.help.owner')"
                        >
                            {{ t('products.sdl.fields.owner') }}
                        </FieldLabel>
                        <select
                            id="owner_user_id"
                            v-model="form.owner_user_id"
                            :class="selectClass"
                        >
                            <option value="">
                                {{ t('products.sdl.none_selected') }}
                            </option>
                            <option
                                v-for="member in props.members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.owner_user_id" />
                    </div>
                </div>

                <div class="space-y-2">
                    <FieldLabel
                        html-for="notes"
                        :help="t('products.sdl.help.notes')"
                    >
                        {{ t('products.sdl.fields.notes') }}
                    </FieldLabel>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        :class="textareaClass"
                        rows="4"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="space-y-2">
                    <FieldLabel :help="t('products.sdl.help.evidence')">
                        {{ t('products.sdl.fields.evidence') }}
                    </FieldLabel>
                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <p
                            v-if="props.evidence.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.sdl.no_evidence') }}
                        </p>
                        <label
                            v-for="item in props.evidence"
                            :key="item.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="form.evidence_ids.includes(item.id)"
                                @change="
                                    toggleRunEvidence(
                                        item.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>{{ item.title }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.evidence_ids" />
                </div>
            </fieldset>

            <div
                v-if="props.run.is_approved || props.run.approved_at"
                class="rounded-md border p-3 text-sm text-muted-foreground"
            >
                <p>
                    {{ t('products.sdl.approved_banner') }}
                    <span v-if="props.run.approved_by_name">
                        — {{ props.run.approved_by_name }}
                    </span>
                </p>
            </div>

            <div v-if="props.canManage" class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <section class="space-y-3">
            <div>
                <h2 class="text-sm font-medium">
                    {{ t('products.sdl.stages_heading') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.sdl.stages_help') }}
                </p>
            </div>

            <ul class="space-y-3">
                <li
                    v-for="entry in props.run.stage_entries"
                    :key="entry.stage"
                    class="space-y-3 rounded-md border p-3"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h3 class="text-sm font-medium">
                            {{ enumLabel('stages', entry.stage) }}
                        </h3>
                        <p
                            v-if="entry.completed_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{ stageCompletedLabel(entry) }}
                        </p>
                    </div>

                    <fieldset
                        class="grid gap-3 sm:grid-cols-2"
                        :disabled="!props.canManage"
                    >
                        <div class="space-y-2">
                            <FieldLabel
                                :html-for="`stage-status-${entry.stage}`"
                                :help="t('products.sdl.help.stage_status')"
                            >
                                {{ t('products.sdl.fields.stage_status') }}
                            </FieldLabel>
                            <select
                                :id="`stage-status-${entry.stage}`"
                                v-model="stageDrafts[entry.stage].status"
                                :class="selectClass"
                            >
                                <option
                                    v-for="status in props.options
                                        .stage_statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ enumLabel('stage_statuses', status) }}
                                </option>
                            </select>
                            <InputError
                                :message="stageErrors[entry.stage]?.status"
                            />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <FieldLabel
                                :html-for="`stage-notes-${entry.stage}`"
                                :help="t('products.sdl.help.stage_notes')"
                            >
                                {{ t('products.sdl.fields.stage_notes') }}
                            </FieldLabel>
                            <textarea
                                :id="`stage-notes-${entry.stage}`"
                                v-model="stageDrafts[entry.stage].notes"
                                :class="textareaClass"
                                rows="2"
                            />
                            <InputError
                                :message="stageErrors[entry.stage]?.notes"
                            />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <FieldLabel
                                :help="t('products.sdl.help.stage_evidence')"
                            >
                                {{ t('products.sdl.fields.stage_evidence') }}
                            </FieldLabel>
                            <div
                                class="max-h-36 space-y-2 overflow-y-auto rounded-md border p-3"
                            >
                                <p
                                    v-if="props.evidence.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ t('products.sdl.no_evidence') }}
                                </p>
                                <label
                                    v-for="item in props.evidence"
                                    :key="`${entry.stage}-${item.id}`"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-1"
                                        :checked="
                                            stageDrafts[
                                                entry.stage
                                            ].evidence_ids.includes(item.id)
                                        "
                                        @change="
                                            toggleStageEvidence(
                                                entry.stage,
                                                item.id,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    <span>{{ item.title }}</span>
                                </label>
                            </div>
                            <InputError
                                :message="
                                    stageErrors[entry.stage]?.evidence_ids
                                "
                            />
                        </div>
                    </fieldset>

                    <div v-if="props.canManage" class="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="savingStage === entry.stage"
                            @click="saveStage(entry.stage)"
                        >
                            <Save class="h-4 w-4" />
                            {{ t('products.sdl.stage_save') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
