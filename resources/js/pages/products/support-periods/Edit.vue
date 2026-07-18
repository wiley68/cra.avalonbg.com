<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    destroy,
    index as periodsIndex,
    update,
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

type PeriodPayload = {
    id: number;
    type: string;
    starts_at: string;
    ends_at: string;
    basis: string | null;
    is_extended: boolean;
    exceptions_notes: string | null;
    version_ids: number[];
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    period: PeriodPayload;
    versions: VersionOption[];
    options: { types: string[] };
}>();

const { t } = useTranslations();

const form = useForm({
    type: props.period.type,
    starts_at: props.period.starts_at,
    ends_at: props.period.ends_at,
    basis: props.period.basis ?? '',
    is_extended: props.period.is_extended,
    exceptions_notes: props.period.exceptions_notes ?? '',
    version_ids: [...props.period.version_ids],
});

const submit = () => {
    form.put(
        update({
            product: props.product.id,
            support_period: props.period.id,
        }).url,
    );
};

const remove = () => {
    if (!confirm(t('products.support_periods.confirm_delete'))) {
        return;
    }

    router.delete(
        destroy({
            product: props.product.id,
            support_period: props.period.id,
        }).url,
    );
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
    <Head :title="t('products.support_periods.edit_title')" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.support_periods.edit_title') }}
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
                <InputError :message="form.errors.version_ids" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Button type="button" variant="destructive" @click="remove">
                    {{ t('common.delete') }}
                </Button>
            </div>
        </form>
    </div>
</template>
