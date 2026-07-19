<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as controlsIndex, store } from '@/routes/controls';
import { create as controlsCreate } from '@/routes/controls';

type Member = {
    id: number;
    name: string;
    email: string;
};

type RequirementOption = {
    id: number;
    code: string;
    article_ref: string | null;
};

const props = defineProps<{
    members: Member[];
    requirements: RequirementOption[];
    options: {
        automation_levels: string[];
        frequencies: string[];
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.controls', href: controlsIndex() },
    { titleKey: 'controls.create_title', href: controlsCreate() },
]);

const form = useForm({
    code: '',
    name: '',
    description: '',
    owner_user_id: '' as number | '',
    implementation_guidance: '',
    automation_level: props.options.automation_levels[0] ?? 'manual',
    frequency: props.options.frequencies[0] ?? 'per_release',
    is_active: true,
    requirement_ids: [] as number[],
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        owner_user_id: data.owner_user_id || null,
    })).post(store().url);
};

const enumLabel = (group: string, value: string): string => {
    const key = `controls.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const toggleRequirement = (id: number, checked: boolean) => {
    if (checked) {
        if (!form.requirement_ids.includes(id)) {
            form.requirement_ids = [...form.requirement_ids, id];
        }

        return;
    }

    form.requirement_ids = form.requirement_ids.filter((value) => value !== id);
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('controls.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('controls.create_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link :href="controlsIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="code"
                        required
                        :help="t('controls.help.code')"
                    >
                        {{ t('controls.fields.code') }}
                    </FieldLabel>
                    <Input id="code" v-model="form.code" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="name"
                        required
                        :help="t('controls.help.name')"
                    >
                        {{ t('common.name') }}
                    </FieldLabel>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="description"
                    :help="t('controls.help.description')"
                >
                    {{ t('controls.fields.description') }}
                </FieldLabel>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="implementation_guidance"
                    :help="t('controls.help.implementation_guidance')"
                >
                    {{ t('controls.fields.implementation_guidance') }}
                </FieldLabel>
                <textarea
                    id="implementation_guidance"
                    v-model="form.implementation_guidance"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.implementation_guidance" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="automation_level"
                        required
                        :help="t('controls.help.automation_level')"
                    >
                        {{ t('controls.fields.automation_level') }}
                    </FieldLabel>
                    <select
                        id="automation_level"
                        v-model="form.automation_level"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option
                            v-for="value in options.automation_levels"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('automation_levels', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.automation_level" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="frequency"
                        required
                        :help="t('controls.help.frequency')"
                    >
                        {{ t('controls.fields.frequency') }}
                    </FieldLabel>
                    <select
                        id="frequency"
                        v-model="form.frequency"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option
                            v-for="value in options.frequencies"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('frequencies', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.frequency" />
                </div>
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="owner_user_id"
                    :help="t('controls.help.owner')"
                >
                    {{ t('controls.fields.owner') }}
                </FieldLabel>
                <select
                    id="owner_user_id"
                    v-model="form.owner_user_id"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option value="">{{ t('products.none') }}</option>
                    <option
                        v-for="member in members"
                        :key="member.id"
                        :value="member.id"
                    >
                        {{ member.name }} ({{ member.email }})
                    </option>
                </select>
                <InputError :message="form.errors.owner_user_id" />
            </div>

            <div
                class="flex items-center justify-between gap-4 rounded-md border p-3"
            >
                <FieldLabel
                    html-for="is_active"
                    :help="t('controls.help.is_active')"
                >
                    {{ t('controls.fields.is_active') }}
                </FieldLabel>
                <Switch id="is_active" v-model="form.is_active" />
            </div>

            <div class="grid gap-2">
                <FieldLabel :help="t('controls.help.requirements')">
                    {{ t('controls.fields.requirements') }}
                </FieldLabel>
                <div
                    class="max-h-56 space-y-2 overflow-y-auto rounded-md border p-3"
                >
                    <label
                        v-for="requirement in requirements"
                        :key="requirement.id"
                        class="flex items-start gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            class="mt-1"
                            :checked="
                                form.requirement_ids.includes(requirement.id)
                            "
                            @change="
                                toggleRequirement(
                                    requirement.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span>
                            <span class="font-medium">{{
                                requirement.code
                            }}</span>
                            <span
                                v-if="requirement.article_ref"
                                class="text-muted-foreground"
                            >
                                — {{ requirement.article_ref }}
                            </span>
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.requirement_ids" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <Plus class="h-4 w-4" />
                {{ t('common.create') }}
            </Button>
        </form>
    </div>
</template>
