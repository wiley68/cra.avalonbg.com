<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import {
    destroy,
    index as versionsIndex,
    update,
} from '@/routes/products/versions';

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

type PreviousVersion = {
    id: number;
    version_number: string;
};

type Options = {
    states: string[];
    support_statuses: string[];
};

type EditableVersion = {
    id: number;
    version_number: string;
    release_date: string | null;
    state: string;
    support_status: string;
    security_support_deadline: string | null;
    git_ref: string | null;
    build_identifier: string | null;
    artifact_hash: string | null;
    changelog: string | null;
    previous_version_id: number | null;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    version: EditableVersion;
    previousVersions: PreviousVersion[];
    options: Options;
}>();

const { t } = useTranslations();
const showDeleteDialog = ref(false);

const form = useForm({
    version_number: props.version.version_number,
    release_date: props.version.release_date ?? '',
    state: props.version.state,
    support_status: props.version.support_status,
    security_support_deadline: props.version.security_support_deadline ?? '',
    git_ref: props.version.git_ref ?? '',
    build_identifier: props.version.build_identifier ?? '',
    artifact_hash: props.version.artifact_hash ?? '',
    changelog: props.version.changelog ?? '',
    previous_version_id: (props.version.previous_version_id ?? '') as
        number | '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        release_date: data.release_date || null,
        security_support_deadline: data.security_support_deadline || null,
        previous_version_id: data.previous_version_id || null,
    })).put(
        update({
            product: props.product.id,
            version: props.version.id,
        }).url,
    );
};

const confirmDelete = () => {
    showDeleteDialog.value = false;
    router.delete(
        destroy({
            product: props.product.id,
            version: props.version.id,
        }).url,
    );
};

const labelFor = (group: string, value: string): string => {
    const key = `products.versions.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none';
</script>

<template>
    <Head :title="t('products.versions.edit_title')" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.versions.edit_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="versionsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="version_number">{{
                    t('products.versions.fields.version_number')
                }}</Label>
                <Input
                    id="version_number"
                    v-model="form.version_number"
                    required
                />
                <InputError :message="form.errors.version_number" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="state">{{
                        t('products.versions.fields.state')
                    }}</Label>
                    <select
                        id="state"
                        v-model="form.state"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="value in options.states"
                            :key="value"
                            :value="value"
                        >
                            {{ labelFor('states', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.state" />
                </div>
                <div class="grid gap-2">
                    <Label for="support_status">{{
                        t('products.versions.fields.support_status')
                    }}</Label>
                    <select
                        id="support_status"
                        v-model="form.support_status"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="value in options.support_statuses"
                            :key="value"
                            :value="value"
                        >
                            {{ labelFor('support', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.support_status" />
                </div>
                <div class="grid gap-2">
                    <Label for="release_date">{{
                        t('products.versions.fields.release_date')
                    }}</Label>
                    <Input
                        id="release_date"
                        v-model="form.release_date"
                        type="date"
                    />
                    <InputError :message="form.errors.release_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="security_support_deadline">{{
                        t('products.versions.fields.security_support_deadline')
                    }}</Label>
                    <Input
                        id="security_support_deadline"
                        v-model="form.security_support_deadline"
                        type="date"
                    />
                    <InputError
                        :message="form.errors.security_support_deadline"
                    />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="git_ref">{{
                        t('products.versions.fields.git_ref')
                    }}</Label>
                    <Input id="git_ref" v-model="form.git_ref" />
                    <InputError :message="form.errors.git_ref" />
                </div>
                <div class="grid gap-2">
                    <Label for="build_identifier">{{
                        t('products.versions.fields.build_identifier')
                    }}</Label>
                    <Input
                        id="build_identifier"
                        v-model="form.build_identifier"
                    />
                    <InputError :message="form.errors.build_identifier" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="artifact_hash">{{
                        t('products.versions.fields.artifact_hash')
                    }}</Label>
                    <Input id="artifact_hash" v-model="form.artifact_hash" />
                    <InputError :message="form.errors.artifact_hash" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="previous_version_id">{{
                        t('products.versions.fields.previous_version')
                    }}</Label>
                    <select
                        id="previous_version_id"
                        v-model="form.previous_version_id"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">{{ t('products.none') }}</option>
                        <option
                            v-for="version in previousVersions"
                            :key="version.id"
                            :value="version.id"
                        >
                            {{ version.version_number }}
                        </option>
                    </select>
                    <InputError :message="form.errors.previous_version_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="changelog">{{
                    t('products.versions.fields.changelog')
                }}</Label>
                <textarea
                    id="changelog"
                    v-model="form.changelog"
                    rows="4"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.changelog" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="showDeleteDialog = true"
                >
                    <Trash2 class="h-4 w-4" />
                    {{ t('common.delete') }}
                </Button>
            </div>
        </form>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.versions.confirm_delete')"
            @confirm="confirmDelete"
        />
    </div>
</template>
