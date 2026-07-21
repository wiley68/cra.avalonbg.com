<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
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
import { Switch } from '@/components/ui/switch';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    edit as customersEdit,
    index as customersIndex,
    update,
} from '@/routes/customers';

type CustomerPayload = {
    id: number;
    name: string;
    external_ref: string | null;
    primary_contact: string | null;
    criticality: string;
    notes: string | null;
    is_active: boolean;
};

const props = defineProps<{
    customer: CustomerPayload;
    options: {
        criticalities: string[];
    };
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.customers', href: customersIndex() },
    { title: props.customer.name, href: customersEdit(props.customer.id) },
]);

const form = useForm({
    name: props.customer.name,
    external_ref: props.customer.external_ref ?? '',
    primary_contact: props.customer.primary_contact ?? '',
    criticality: props.customer.criticality,
    notes: props.customer.notes ?? '',
    is_active: props.customer.is_active,
});

const submit = () => {
    form.put(update(props.customer.id).url);
};

const criticalityLabel = (value: string): string => {
    const key = `customers.criticalities.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('customers.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('customers.edit_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link :href="customersIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form
            class="space-y-5 rounded-lg border p-6"
            @submit.prevent="canManage && submit()"
        >
            <fieldset :disabled="!canManage" class="space-y-5">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="name"
                        :help="t('customers.help.name')"
                        required
                    >
                        {{ t('common.name') }}
                    </FieldLabel>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="external_ref">{{
                        t('customers.fields.external_ref')
                    }}</Label>
                    <Input id="external_ref" v-model="form.external_ref" />
                    <p class="text-sm text-muted-foreground">
                        {{ t('customers.help.external_ref') }}
                    </p>
                    <InputError :message="form.errors.external_ref" />
                </div>

                <div class="grid gap-2">
                    <Label for="primary_contact">{{
                        t('customers.fields.primary_contact')
                    }}</Label>
                    <Input
                        id="primary_contact"
                        v-model="form.primary_contact"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ t('customers.help.primary_contact') }}
                    </p>
                    <InputError :message="form.errors.primary_contact" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="criticality"
                        :help="t('customers.help.criticality')"
                        required
                    >
                        {{ t('customers.fields.criticality') }}
                    </FieldLabel>
                    <Select v-model="form.criticality">
                        <SelectTrigger id="criticality" class="w-full max-w-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="value in options.criticalities"
                                :key="value"
                                :value="value"
                            >
                                {{ criticalityLabel(value) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.criticality" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">{{ t('customers.fields.notes') }}</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        :class="textareaClass"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <div
                    class="flex items-center justify-between gap-4 rounded-lg border p-4"
                >
                    <div class="space-y-0.5">
                        <Label for="is_active">{{
                            t('customers.fields.is_active')
                        }}</Label>
                        <p class="text-sm text-muted-foreground">
                            {{ t('customers.help.is_active') }}
                        </p>
                    </div>
                    <Switch id="is_active" v-model="form.is_active" />
                </div>
                <InputError :message="form.errors.is_active" />
            </fieldset>

            <Button v-if="canManage" type="submit" :disabled="form.processing">
                <Save class="h-4 w-4" />
                {{ t('common.save') }}
            </Button>
        </form>
    </div>
</template>
