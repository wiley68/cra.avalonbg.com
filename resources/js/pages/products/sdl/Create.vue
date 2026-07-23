<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    create as productSdlCreate,
    index as productSdlIndex,
    store,
} from '@/routes/products/sdl';
import { edit as editProduct, index as productsIndex } from '@/routes/products';

type Member = { id: number; name: string; email: string };
type VersionOption = { id: number; version_number: string };
type EvidenceOption = { id: number; title: string; type?: string };
type ProductSummary = { id: number; name: string; slug: string };
type RepositoryPayload = {
    id: number;
    full_name: string;
    remote_url: string;
    last_sync_summary: {
        ci?: {
            html_url?: string | null;
            conclusion?: string | null;
            status?: string;
        };
        evidence_id?: number;
    } | null;
} | null;
type GitEvidenceOption = {
    id: number;
    title: string;
    checksum_short: string | null;
};

const props = defineProps<{
    product: ProductSummary;
    members: Member[];
    versions: VersionOption[];
    evidence: EvidenceOption[];
    repository?: RepositoryPayload;
    git_evidence?: GitEvidenceOption[];
    options: {
        statuses: string[];
        stages: string[];
        locales: string[];
        default_locale: string;
        template_stages: string[];
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
        titleKey: 'products.sdl.create_title',
        href: productSdlCreate(props.product.id),
    },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const form = useForm({
    title: '',
    status:
        props.options.statuses.find((status) => status !== 'approved') ??
        'draft',
    current_stage: props.options.stages[0] ?? 'requirement',
    product_version_id: '' as number | '',
    owner_user_id: '' as number | '',
    notes: '',
    use_template: false,
    locale: props.options.default_locale || props.options.locales[0] || 'en',
    evidence_ids: [] as number[],
});

const createStatuses = props.options.statuses.filter(
    (status) => status !== 'approved',
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_version_id: data.product_version_id || null,
        owner_user_id: data.owner_user_id || null,
    })).post(store(props.product.id).url);
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.sdl.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const localeLabel = (value: string): string => {
    const key = `products.sdl.locales.${value}`;
    const translated = t(key);

    return translated === key ? value.toUpperCase() : translated;
};

const toggleEvidence = (id: number, checked: boolean) => {
    if (checked) {
        if (!form.evidence_ids.includes(id)) {
            form.evidence_ids.push(id);
        }

        return;
    }

    form.evidence_ids = form.evidence_ids.filter((value) => value !== id);
};
</script>

<template>
    <Head :title="t('products.sdl.create_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.sdl.create_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.sdl.create_help') }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="productSdlIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div
                class="flex items-center justify-between gap-4 rounded-md border p-3"
            >
                <div class="space-y-1">
                    <FieldLabel
                        html-for="use_template"
                        :help="t('products.sdl.help.use_template')"
                    >
                        {{ t('products.sdl.fields.use_template') }}
                    </FieldLabel>
                </div>
                <Switch id="use_template" v-model="form.use_template" />
            </div>

            <div v-if="form.use_template" class="space-y-2">
                <FieldLabel
                    html-for="locale"
                    :help="t('products.sdl.help.locale')"
                >
                    {{ t('products.sdl.fields.locale') }}
                </FieldLabel>
                <select id="locale" v-model="form.locale" :class="selectClass">
                    <option
                        v-for="locale in props.options.locales"
                        :key="locale"
                        :value="locale"
                    >
                        {{ localeLabel(locale) }}
                    </option>
                </select>
                <InputError :message="form.errors.locale" />
                <p class="text-sm text-muted-foreground">
                    {{
                        t('products.sdl.template_stages_hint', {
                            stages: props.options.template_stages
                                .map((stage) => enumLabel('stages', stage))
                                .join(', '),
                        })
                    }}
                </p>
            </div>

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
                            v-for="status in createStatuses"
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
                        :help="t('products.sdl.help.current_stage')"
                    >
                        {{ t('products.sdl.fields.current_stage') }}
                    </FieldLabel>
                    <select
                        id="current_stage"
                        v-model="form.current_stage"
                        :class="selectClass"
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
                    v-if="
                        props.repository ||
                        (props.git_evidence?.length ?? 0) > 0
                    "
                    class="mb-2 space-y-2 rounded-md border p-3 text-sm"
                >
                    <p class="font-medium">
                        {{ t('products.sdl.git_heading') }}
                    </p>
                    <p class="text-muted-foreground">
                        {{ t('products.sdl.git_help') }}
                    </p>
                    <p v-if="props.repository">
                        <a
                            :href="props.repository.remote_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline-offset-4 hover:underline"
                        >
                            {{ props.repository.full_name }}
                        </a>
                    </p>
                    <p
                        v-if="(props.git_evidence?.length ?? 0) > 0"
                        class="text-muted-foreground"
                    >
                        {{ t('products.sdl.git_recent_snapshots') }}:
                        {{
                            props.git_evidence
                                ?.map((item) => `#${item.id}`)
                                .join(', ')
                        }}
                    </p>
                </div>
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
                                toggleEvidence(
                                    item.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span>{{ item.title }}</span>
                    </label>
                </div>
                <InputError :message="form.errors.evidence_ids" />
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('products.sdl.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
