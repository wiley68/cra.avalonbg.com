<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import { computed, watch } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as auditorIndex } from '@/routes/auditor';
import {
    create as packagesCreate,
    store as packagesStore,
} from '@/routes/auditor/packages';

type EvidenceOption = {
    id: number;
    title: string;
    type: string;
};

type ProductOption = {
    id: number;
    name: string;
    evidence: EvidenceOption[];
};

const props = defineProps<{
    products: ProductOption[];
    preselected_product_id?: number | null;
    preselected_evidence_ids?: number[];
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.auditor', href: auditorIndex() },
    { titleKey: 'auditor.create_title', href: packagesCreate() },
]);

const initialProductId = (() => {
    if (
        props.preselected_product_id &&
        props.products.some(
            (product) => product.id === props.preselected_product_id,
        )
    ) {
        return String(props.preselected_product_id);
    }

    return props.products[0]?.id ? String(props.products[0].id) : '';
})();

const initialEvidenceIds = (() => {
    const product = props.products.find(
        (item) => String(item.id) === initialProductId,
    );

    if (!product) {
        return [] as number[];
    }

    const allowed = new Set(product.evidence.map((item) => item.id));

    return (props.preselected_evidence_ids ?? []).filter((id) =>
        allowed.has(id),
    );
})();

const form = useForm({
    product_id: initialProductId,
    title: '',
    notes: '',
    evidence_ids: [...initialEvidenceIds] as number[],
});

const selectedProduct = computed(() =>
    props.products.find((product) => String(product.id) === form.product_id),
);

const availableEvidence = computed(() => selectedProduct.value?.evidence ?? []);

const hasPreselectedEvidence = computed(() => initialEvidenceIds.length > 0);

watch(
    () => form.product_id,
    () => {
        form.evidence_ids = [];
    },
);

const toggleEvidence = (evidenceId: number, checked: boolean): void => {
    if (checked) {
        if (!form.evidence_ids.includes(evidenceId)) {
            form.evidence_ids.push(evidenceId);
        }

        return;
    }

    form.evidence_ids = form.evidence_ids.filter((id) => id !== evidenceId);
};

const evidenceTypeLabel = (value: string): string => {
    const key = `products.evidence.types.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_id: data.product_id ? Number(data.product_id) : null,
    })).post(packagesStore().url);
};
</script>

<template>
    <Head :title="t('auditor.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('auditor.create_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link :href="auditorIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <p
            v-if="hasPreselectedEvidence"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            {{
                t('auditor.preselected_evidence_hint', {
                    count: String(initialEvidenceIds.length),
                })
            }}
        </p>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="product_id"
                    :help="t('auditor.help.product')"
                    required
                >
                    {{ t('auditor.fields.product') }}
                </FieldLabel>
                <Select v-model="form.product_id">
                    <SelectTrigger id="product_id" class="w-full">
                        <SelectValue
                            :placeholder="t('auditor.select_product')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="product in products"
                            :key="product.id"
                            :value="String(product.id)"
                        >
                            {{ product.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.product_id" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="title"
                    :help="t('auditor.help.title')"
                    required
                >
                    {{ t('auditor.fields.title') }}
                </FieldLabel>
                <Input id="title" v-model="form.title" required />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <FieldLabel html-for="notes" :help="t('auditor.help.notes')">
                    {{ t('auditor.fields.notes') }}
                </FieldLabel>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="grid gap-2">
                <FieldLabel :help="t('auditor.help.evidence')">
                    {{ t('auditor.fields.evidence') }}
                </FieldLabel>
                <div
                    v-if="availableEvidence.length"
                    class="max-h-56 space-y-2 overflow-y-auto rounded-md border p-3"
                >
                    <label
                        v-for="item in availableEvidence"
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
                        <span>
                            <span class="font-medium">{{ item.title }}</span>
                            <span class="text-muted-foreground">
                                — {{ evidenceTypeLabel(item.type) }}
                            </span>
                        </span>
                    </label>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('auditor.no_evidence') }}
                </p>
                <InputError :message="form.errors.evidence_ids" />
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('auditor.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
