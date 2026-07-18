<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    index as periodsIndex,
    store,
} from '@/routes/products/support-periods';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProductSummary = {
    id: number;
    name: string;
    slug: string;
};

type VersionOption = {
    id: number;
    version_number: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    versions: VersionOption[];
    options: { types: string[] };
}>();

const { t } = useTranslations();

const form = useForm({
    type: props.options.types[0] ?? 'commercial',
    starts_at: '',
    ends_at: '',
    basis: '',
    is_extended: false,
    exceptions_notes: '',
    version_ids: [] as number[],
});

const submit = () => {
    form.post(store(props.product.id).url);
};

const typeLabel = (type: string): string => {
    const key = `products.support_periods.types.${type}`;
    const translated = t(key);

    return translated === key ? type : translated;
};

const toggleVersion = (versionId: number, checked: boolean): void => {
    if (checked) {
        if (!form.version_ids.includes(versionId)) {
            form.version_ids.push(versionId);
        }

        return;
    }

    form.version_ids = form.version_ids.filter((id) => id !== versionId);
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none';
</script>

<template>
    <Head :title="t('products.support_periods.create_title')" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.support_periods.create_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="periodsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="type"
                    required
                    :help="t('products.support_periods.help.type')"
                    >{{ t('products.support_periods.fields.type') }}</FieldLabel
                >
                <select
                    id="type"
                    v-model="form.type"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option
                        v-for="type in options.types"
                        :key="type"
                        :value="type"
                    >
                        {{ typeLabel(type) }}
                    </option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="starts_at"
                        required
                        :help="t('products.support_periods.help.starts_at')"
                        >{{
                            t('products.support_periods.fields.starts_at')
                        }}</FieldLabel
                    >
                    <Input
                        id="starts_at"
                        v-model="form.starts_at"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.starts_at" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="ends_at"
                        required
                        :help="t('products.support_periods.help.ends_at')"
                        >{{
                            t('products.support_periods.fields.ends_at')
                        }}</FieldLabel
                    >
                    <Input
                        id="ends_at"
                        v-model="form.ends_at"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.ends_at" />
                </div>
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="basis"
                    :help="t('products.support_periods.help.basis')"
                    >{{
                        t('products.support_periods.fields.basis')
                    }}</FieldLabel
                >
                <textarea
                    id="basis"
                    v-model="form.basis"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.basis" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    :checked="form.is_extended"
                    @update:checked="form.is_extended = Boolean($event)"
                />
                {{ t('products.support_periods.fields.is_extended') }}
            </label>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="exceptions_notes"
                    :help="t('products.support_periods.help.exceptions_notes')"
                    >{{
                        t('products.support_periods.fields.exceptions_notes')
                    }}</FieldLabel
                >
                <textarea
                    id="exceptions_notes"
                    v-model="form.exceptions_notes"
                    rows="2"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.exceptions_notes" />
            </div>

            <div class="space-y-2">
                <FieldLabel
                    :help="t('products.support_periods.help.versions')"
                    >{{
                        t('products.support_periods.fields.versions')
                    }}</FieldLabel
                >
                <div
                    v-for="version in versions"
                    :key="version.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :checked="form.version_ids.includes(version.id)"
                        @update:checked="
                            toggleVersion(version.id, Boolean($event))
                        "
                    />
                    {{ version.version_number }}
                </div>
                <p
                    v-if="versions.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('products.support_periods.no_versions') }}
                </p>
                <InputError :message="form.errors.version_ids" />
            </div>

            <Button type="submit" :disabled="form.processing">
                {{ t('common.create') }}
            </Button>
        </form>
    </div>
</template>
