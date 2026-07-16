<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import {
    index as organizationsIndex,
    store,
} from '@/routes/admin/organizations';

const { t } = useTranslations();

const form = useForm({
    name: '',
    slug: '',
    billing_email: '',
    subscription_plan: '',
    is_active: true,
    create_owner: true,
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
                <Link :href="organizationsIndex()">{{ t('common.back') }}</Link>
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

            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    :checked="form.is_active"
                    @update:checked="form.is_active = Boolean($event)"
                />
                {{ t('admin.organizations.active') }}
            </label>

            <div class="space-y-4 border-t pt-4">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <Checkbox
                        :checked="form.create_owner"
                        @update:checked="form.create_owner = Boolean($event)"
                    />
                    {{ t('admin.organizations.create_owner') }}
                </label>

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
                {{ t('common.create') }}
            </Button>
        </form>
    </div>
</template>
