<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import { computed, watch } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import PolicyBodyField from '@/components/PolicyBodyField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    create as policiesCreate,
    index as policiesIndex,
    store,
    template as policiesTemplate,
} from '@/routes/policies';

type SupersedeOption = {
    id: number;
    title: string;
    policy_type: string;
    version_label: string;
    status: string;
    body: string;
};

const props = defineProps<{
    options: {
        policy_types: string[];
        statuses: string[];
    };
    supersedeOptions: SupersedeOption[];
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.policies', href: policiesIndex() },
    { titleKey: 'policies.create_title', href: policiesCreate() },
]);

const form = useForm({
    policy_type: props.options.policy_types[0] ?? 'vulnerability_disclosure',
    title: '',
    version_label: '1.0',
    body: '',
    notes: '',
    supersedes_id: null as number | null,
    use_template: true,
});

const filteredSupersedeOptions = computed(() =>
    props.supersedeOptions.filter(
        (option) => option.policy_type === form.policy_type,
    ),
);

const selectedSupersede = computed(() =>
    filteredSupersedeOptions.value.find(
        (option) => option.id === form.supersedes_id,
    ),
);

const typeLabel = (value: string): string => {
    const key = `policies.types.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const loadTemplate = async (): Promise<void> => {
    if (!form.use_template || !form.policy_type) {
        return;
    }

    const response = await fetch(
        `${policiesTemplate().url}?policy_type=${encodeURIComponent(form.policy_type)}`,
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        },
    );

    if (!response.ok) {
        return;
    }

    const data = (await response.json()) as {
        title: string;
        body: string;
        version_label: string;
    };

    form.title = data.title;
    form.body = data.body;
    form.version_label = data.version_label;
};

watch(
    () => [form.policy_type, form.use_template] as const,
    () => {
        form.supersedes_id = null;
        void loadTemplate();
    },
    { immediate: true },
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        supersedes_id: data.supersedes_id || null,
    })).post(store().url);
};
</script>

<template>
    <Head :title="t('policies.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('policies.create_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link :href="policiesIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="policy_type"
                    :help="t('policies.help.policy_type')"
                    required
                >
                    {{ t('policies.fields.policy_type') }}
                </FieldLabel>
                <Select v-model="form.policy_type">
                    <SelectTrigger id="policy_type" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="value in options.policy_types"
                            :key="value"
                            :value="value"
                        >
                            {{ typeLabel(value) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.policy_type" />
            </div>

            <div
                class="flex items-center justify-between gap-4 rounded-md border px-3 py-2"
            >
                <div>
                    <Label for="use_template">{{
                        t('policies.fields.use_template')
                    }}</Label>
                    <p class="text-sm text-muted-foreground">
                        {{ t('policies.help.use_template') }}
                    </p>
                </div>
                <Switch id="use_template" v-model="form.use_template" />
            </div>

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

            <div v-if="filteredSupersedeOptions.length" class="grid gap-2">
                <Label for="supersedes_id">{{
                    t('policies.fields.supersedes')
                }}</Label>
                <Select
                    :model-value="
                        form.supersedes_id
                            ? String(form.supersedes_id)
                            : undefined
                    "
                    @update:model-value="
                        (value) => {
                            form.supersedes_id = value ? Number(value) : null;
                        }
                    "
                >
                    <SelectTrigger id="supersedes_id" class="w-full">
                        <SelectValue
                            :placeholder="t('policies.supersedes_none')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in filteredSupersedeOptions"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.title }} ({{ option.version_label }})
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.supersedes_id" />
            </div>

            <PolicyBodyField
                v-model="form.body"
                :previous-body="selectedSupersede?.body ?? null"
                :previous-label="
                    selectedSupersede
                        ? `${selectedSupersede.title} (${selectedSupersede.version_label})`
                        : null
                "
                :current-label="form.version_label"
                :error="form.errors.body"
                required
            />

            <div class="grid gap-2">
                <Label for="notes">{{ t('policies.fields.notes') }}</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('policies.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
