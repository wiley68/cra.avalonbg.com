<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import {
    index as requirementsIndex,
    update,
} from '@/routes/admin/requirements';

type RegulationOption = {
    id: number;
    code: string;
    title: string;
};

type RequirementVersion = {
    id: number;
    version: number;
    requirement_text: string;
    plain_language: string | null;
    applicability_notes: string | null;
    suggested_controls_text: string | null;
    required_evidence_text: string | null;
    is_current: boolean;
    published_at: string | null;
};

type EditableRequirement = {
    id: number;
    regulation_id: number;
    code: string;
    article_ref: string | null;
    sort_order: number;
    is_active: boolean;
    versions: RequirementVersion[];
};

const props = defineProps<{
    requirement: EditableRequirement;
    regulations: RegulationOption[];
}>();

const { t } = useTranslations();

const currentVersion = computed(
    () =>
        props.requirement.versions.find((version) => version.is_current) ??
        props.requirement.versions[0],
);

const form = useForm({
    regulation_id: props.requirement.regulation_id,
    code: props.requirement.code,
    article_ref: props.requirement.article_ref ?? '',
    sort_order: props.requirement.sort_order,
    is_active: props.requirement.is_active,
    create_new_version: false,
    requirement_text: currentVersion.value?.requirement_text ?? '',
    plain_language: currentVersion.value?.plain_language ?? '',
    applicability_notes: currentVersion.value?.applicability_notes ?? '',
    suggested_controls_text:
        currentVersion.value?.suggested_controls_text ?? '',
    required_evidence_text: currentVersion.value?.required_evidence_text ?? '',
});

const submit = () => {
    form.put(update(props.requirement.id).url);
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('admin.requirements.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('admin.requirements.edit_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link :href="requirementsIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="regulation_id"
                    required
                    :help="t('admin.requirements.help.regulation')"
                >
                    {{ t('admin.requirements.fields.regulation') }}
                </FieldLabel>
                <select
                    id="regulation_id"
                    v-model="form.regulation_id"
                    class="h-9 rounded-md border bg-background px-3"
                    required
                >
                    <option
                        v-for="regulation in regulations"
                        :key="regulation.id"
                        :value="regulation.id"
                    >
                        {{ regulation.code }} — {{ regulation.title }}
                    </option>
                </select>
                <InputError :message="form.errors.regulation_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="code"
                        required
                        :help="t('admin.requirements.help.code')"
                    >
                        {{ t('admin.requirements.fields.code') }}
                    </FieldLabel>
                    <Input id="code" v-model="form.code" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="article_ref"
                        :help="t('admin.requirements.help.article_ref')"
                    >
                        {{ t('admin.requirements.fields.article_ref') }}
                    </FieldLabel>
                    <Input id="article_ref" v-model="form.article_ref" />
                    <InputError :message="form.errors.article_ref" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="sort_order"
                        required
                        :help="t('admin.requirements.help.sort_order')"
                    >
                        {{ t('admin.requirements.fields.sort_order') }}
                    </FieldLabel>
                    <Input
                        id="sort_order"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        required
                    />
                    <InputError :message="form.errors.sort_order" />
                </div>
                <div class="flex items-center gap-3 pt-6">
                    <Switch id="is_active" v-model="form.is_active" />
                    <FieldLabel
                        html-for="is_active"
                        :help="t('admin.requirements.help.is_active')"
                    >
                        {{ t('admin.requirements.fields.is_active') }}
                    </FieldLabel>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Switch
                    id="create_new_version"
                    v-model="form.create_new_version"
                />
                <FieldLabel
                    html-for="create_new_version"
                    :help="t('admin.requirements.help.create_new_version')"
                >
                    {{ t('admin.requirements.fields.create_new_version') }}
                </FieldLabel>
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="requirement_text"
                    required
                    :help="t('admin.requirements.help.requirement_text')"
                >
                    {{ t('admin.requirements.fields.requirement_text') }}
                </FieldLabel>
                <textarea
                    id="requirement_text"
                    v-model="form.requirement_text"
                    rows="4"
                    required
                    :class="textareaClass"
                />
                <InputError :message="form.errors.requirement_text" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="plain_language"
                    :help="t('admin.requirements.help.plain_language')"
                >
                    {{ t('admin.requirements.fields.plain_language') }}
                </FieldLabel>
                <textarea
                    id="plain_language"
                    v-model="form.plain_language"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.plain_language" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="applicability_notes"
                    :help="t('admin.requirements.help.applicability_notes')"
                >
                    {{ t('admin.requirements.fields.applicability_notes') }}
                </FieldLabel>
                <textarea
                    id="applicability_notes"
                    v-model="form.applicability_notes"
                    rows="2"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.applicability_notes" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="suggested_controls_text"
                    :help="t('admin.requirements.help.suggested_controls_text')"
                >
                    {{ t('admin.requirements.fields.suggested_controls_text') }}
                </FieldLabel>
                <textarea
                    id="suggested_controls_text"
                    v-model="form.suggested_controls_text"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.suggested_controls_text" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="required_evidence_text"
                    :help="t('admin.requirements.help.required_evidence_text')"
                >
                    {{ t('admin.requirements.fields.required_evidence_text') }}
                </FieldLabel>
                <textarea
                    id="required_evidence_text"
                    v-model="form.required_evidence_text"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.required_evidence_text" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <Save class="h-4 w-4" />
                {{ t('common.save') }}
            </Button>
        </form>

        <section class="space-y-3 rounded-lg border p-6">
            <h2
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ t('admin.requirements.versions_title') }}
            </h2>
            <ul class="space-y-2 text-sm">
                <li
                    v-for="version in requirement.versions"
                    :key="version.id"
                    class="flex items-center justify-between gap-3"
                >
                    <span>
                        v{{ version.version }}
                        <span
                            v-if="version.is_current"
                            class="ml-2 text-muted-foreground"
                        >
                            ({{ t('admin.requirements.current') }})
                        </span>
                    </span>
                    <span class="text-muted-foreground">
                        {{ version.published_at ?? '—' }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
