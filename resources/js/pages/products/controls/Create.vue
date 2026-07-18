<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    index as productControlsIndex,
    store,
} from '@/routes/products/controls';

type ControlOption = {
    id: number;
    code: string;
    name: string;
};

type ProductSummary = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    product: ProductSummary;
    availableControls: ControlOption[];
    options: { statuses: string[] };
}>();

const { t } = useTranslations();

const form = useForm({
    control_id: props.availableControls[0]?.id ?? ('' as number | ''),
    status: props.options.statuses[0] ?? 'planned',
    notes: '',
});

const submit = () => {
    form.post(store(props.product.id).url);
};

const statusLabel = (value: string): string => {
    const key = `products.controls.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('products.controls.assign_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.controls.assign_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productControlsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <p
            v-if="availableControls.length === 0"
            class="rounded-lg border p-6 text-sm text-muted-foreground"
        >
            {{ t('products.controls.no_available') }}
        </p>

        <form
            v-else
            class="space-y-5 rounded-lg border p-6"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <FieldLabel
                    html-for="control_id"
                    required
                    :help="t('products.controls.help.control')"
                >
                    {{ t('products.controls.fields.control') }}
                </FieldLabel>
                <select
                    id="control_id"
                    v-model="form.control_id"
                    class="h-9 rounded-md border bg-background px-3"
                    required
                >
                    <option
                        v-for="control in availableControls"
                        :key="control.id"
                        :value="control.id"
                    >
                        {{ control.code }} — {{ control.name }}
                    </option>
                </select>
                <InputError :message="form.errors.control_id" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="status"
                    required
                    :help="t('products.controls.help.status')"
                >
                    {{ t('products.controls.fields.status') }}
                </FieldLabel>
                <select
                    id="status"
                    v-model="form.status"
                    class="h-9 rounded-md border bg-background px-3"
                    required
                >
                    <option
                        v-for="value in options.statuses"
                        :key="value"
                        :value="value"
                    >
                        {{ statusLabel(value) }}
                    </option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="notes"
                    :help="t('products.controls.help.notes')"
                >
                    {{ t('products.controls.fields.notes') }}
                </FieldLabel>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <Plus class="h-4 w-4" />
                {{ t('products.controls.assign') }}
            </Button>
        </form>
    </div>
</template>
