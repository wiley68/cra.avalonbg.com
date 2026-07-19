<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    index as organizationsIndex,
    store,
} from '@/routes/admin/organizations';
import { create as organizationsCreate } from '@/routes/admin/organizations';

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.organizations', href: organizationsIndex() },
    { titleKey: 'admin.organizations.create_title', href: organizationsCreate() },
]);

const form = useForm({
    name: '',
    slug: '',
    billing_email: '',
    subscription_plan: '',
    is_active: true,
    create_owner: true,
    seed_starter_controls: true,
    owner_name: '',
    owner_email: '',
    owner_password: '',
    owner_password_confirmation: '',
});

const submit = () => {
    form.post(store().url);
};
</script>

<template>
    <Head :title="t('admin.organizations.create_title')" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('admin.organizations.create_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link
                    :href="organizationsIndex()"
                    class="inline-flex items-center gap-2"
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">{{ t('common.name') }}</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">{{ t('admin.organizations.slug') }}</Label>
                <Input id="slug" v-model="form.slug" />
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.organizations.slug_help') }}
                </p>
                <InputError :message="form.errors.slug" />
            </div>

            <div class="grid gap-2">
                <Label for="billing_email">{{
                    t('admin.organizations.billing_email')
                }}</Label>
                <Input
                    id="billing_email"
                    type="email"
                    v-model="form.billing_email"
                />
                <InputError :message="form.errors.billing_email" />
            </div>

            <div class="grid gap-2">
                <Label for="subscription_plan">{{
                    t('admin.organizations.subscription_plan')
                }}</Label>
                <Input
                    id="subscription_plan"
                    v-model="form.subscription_plan"
                />
                <InputError :message="form.errors.subscription_plan" />
            </div>

            <div class="flex items-center gap-3">
                <Switch
                    id="is_active"
                    v-model="form.is_active"
                    class="cursor-pointer"
                />
                <Label for="is_active" class="cursor-pointer">
                    {{
                        form.is_active
                            ? t('admin.organizations.active')
                            : t('admin.organizations.inactive')
                    }}
                </Label>
            </div>

            <div class="space-y-2 rounded-md border border-dashed p-4">
                <div class="flex items-center gap-3">
                    <Switch
                        id="seed_starter_controls"
                        v-model="form.seed_starter_controls"
                        class="cursor-pointer"
                    />
                    <Label
                        for="seed_starter_controls"
                        class="cursor-pointer font-medium"
                    >
                        {{ t('admin.organizations.seed_starter_controls') }}
                    </Label>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.organizations.seed_starter_controls_help') }}
                </p>
            </div>

            <div class="space-y-4 border-t pt-4">
                <div class="flex items-center gap-3">
                    <Switch
                        id="create_owner"
                        v-model="form.create_owner"
                        class="cursor-pointer"
                    />
                    <Label
                        for="create_owner"
                        class="cursor-pointer font-medium"
                    >
                        {{ t('admin.organizations.create_owner') }}
                    </Label>
                </div>

                <template v-if="form.create_owner">
                    <div class="grid gap-2">
                        <Label for="owner_name">{{
                            t('admin.organizations.owner_name')
                        }}</Label>
                        <Input id="owner_name" v-model="form.owner_name" />
                        <InputError :message="form.errors.owner_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="owner_email">{{
                            t('admin.organizations.owner_email')
                        }}</Label>
                        <Input
                            id="owner_email"
                            type="email"
                            v-model="form.owner_email"
                        />
                        <InputError :message="form.errors.owner_email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="owner_password">{{
                            t('admin.users.temporary_password')
                        }}</Label>
                        <PasswordInput
                            id="owner_password"
                            v-model="form.owner_password"
                        />
                        <InputError :message="form.errors.owner_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="owner_password_confirmation">{{
                            t('auth.force_password.confirm_password')
                        }}</Label>
                        <PasswordInput
                            id="owner_password_confirmation"
                            v-model="form.owner_password_confirmation"
                        />
                    </div>
                </template>
            </div>

            <Button type="submit" :disabled="form.processing">
                <Plus class="h-4 w-4" />
                {{ t('common.create') }}
            </Button>
        </form>
    </div>
</template>
