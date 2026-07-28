<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Download, FileText, Mail, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { useTranslations } from '@/composables/useTranslations';

export type BillingDocumentItem = {
    id: number;
    type: string;
    title: string;
    source_filename: string;
    size_bytes: number;
    mime_type: string | null;
    sent_at: string | null;
    sent_to_email: string | null;
    notes: string | null;
    created_at: string | null;
};

const props = withDefaults(
    defineProps<{
        documents: BillingDocumentItem[];
        downloadUrl: (documentId: number) => string;
        canManage?: boolean;
        documentTypes?: string[];
        recipientEmail?: string | null;
        storeUrl?: string | null;
        generateLicenseUrl?: string | null;
        sendUrl?: ((documentId: number) => string) | null;
        destroyUrl?: ((documentId: number) => string) | null;
    }>(),
    {
        canManage: false,
        documentTypes: () => [],
        recipientEmail: null,
        storeUrl: null,
        generateLicenseUrl: null,
        sendUrl: null,
        destroyUrl: null,
    },
);

const { t } = useTranslations();
const generatingLicense = ref(false);

const form = useForm({
    type: props.documentTypes[0] ?? 'invoice',
    title: '',
    notes: '',
    file: null as File | null,
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
};

const submit = () => {
    if (!props.canManage || !props.storeUrl) {
        return;
    }

    form.post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'notes', 'file');
            form.type = props.documentTypes[0] ?? 'invoice';
        },
    });
};

const generateLicense = () => {
    if (!props.canManage || !props.generateLicenseUrl) {
        return;
    }

    generatingLicense.value = true;
    router.post(
        props.generateLicenseUrl,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                generatingLicense.value = false;
            },
        },
    );
};

const sendDocument = (documentId: number) => {
    if (!props.canManage || !props.sendUrl) {
        return;
    }

    router.post(props.sendUrl(documentId), {}, { preserveScroll: true });
};

const deleteDocument = (documentId: number) => {
    if (!props.canManage || !props.destroyUrl) {
        return;
    }

    router.delete(props.destroyUrl(documentId), { preserveScroll: true });
};

const typeLabel = (type: string): string =>
    t(`billing.documents.types.${type}`);
</script>

<template>
    <div class="space-y-4 rounded-lg border p-5">
        <div>
            <h2 class="font-medium">{{ t('billing.documents.title') }}</h2>
            <p class="text-sm text-muted-foreground">
                {{
                    canManage
                        ? t('billing.documents.description_admin')
                        : t('billing.documents.description_tenant')
                }}
            </p>
            <p
                v-if="canManage && recipientEmail"
                class="mt-1 text-xs text-muted-foreground"
            >
                {{ t('billing.documents.recipient') }}:
                <span class="font-medium text-foreground">{{
                    recipientEmail
                }}</span>
            </p>
            <p
                v-else-if="canManage"
                class="mt-1 text-xs text-amber-700 dark:text-amber-400"
            >
                {{ t('billing.documents.no_recipient_hint') }}
            </p>
        </div>

        <form
            v-if="canManage"
            class="grid gap-3 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="billing-doc-type">{{
                    t('billing.documents.type')
                }}</Label>
                <Select v-model="form.type">
                    <SelectTrigger id="billing-doc-type" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="type in documentTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ typeLabel(type) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="billing-doc-title">{{
                    t('billing.documents.doc_title')
                }}</Label>
                <Input
                    id="billing-doc-title"
                    v-model="form.title"
                    type="text"
                    :placeholder="t('billing.documents.doc_title_placeholder')"
                />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="billing-doc-file">{{
                    t('billing.documents.file')
                }}</Label>
                <Input
                    id="billing-doc-file"
                    type="file"
                    @change="onFileChange"
                />
                <InputError :message="form.errors.file" />
            </div>

            <div class="flex flex-wrap gap-2 sm:col-span-2">
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="form.processing"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('billing.documents.upload') }}
                </Button>
                <Button
                    v-if="generateLicenseUrl"
                    type="button"
                    variant="outline"
                    :disabled="generatingLicense"
                    @click="generateLicense"
                >
                    <FileText class="h-4 w-4" />
                    {{ t('billing.documents.generate_license') }}
                </Button>
            </div>
            <p
                v-if="generateLicenseUrl"
                class="text-xs text-muted-foreground sm:col-span-2"
            >
                {{ t('billing.documents.generate_license_help') }}
            </p>
        </form>

        <div
            v-if="documents.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{
                canManage
                    ? t('billing.documents.empty')
                    : t('billing.documents.empty_tenant')
            }}
        </div>

        <ul v-else class="divide-y rounded-md border">
            <li
                v-for="document in documents"
                :key="document.id"
                class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0 space-y-1">
                    <p class="truncate font-medium">
                        {{ document.title }}
                        <span class="text-muted-foreground">
                            · {{ typeLabel(document.type) }}
                        </span>
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ document.source_filename }}
                    </p>
                    <p
                        v-if="canManage && document.sent_at"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('billing.documents.sent_to') }}:
                        {{ document.sent_to_email }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline" size="sm">
                        <a :href="downloadUrl(document.id)">
                            <Download class="h-4 w-4" />
                            {{ t('billing.documents.download') }}
                        </a>
                    </Button>
                    <Button
                        v-if="canManage"
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!recipientEmail"
                        @click="sendDocument(document.id)"
                    >
                        <Mail class="h-4 w-4" />
                        {{ t('billing.documents.send') }}
                    </Button>
                    <Button
                        v-if="canManage"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="deleteDocument(document.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('common.delete') }}
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
