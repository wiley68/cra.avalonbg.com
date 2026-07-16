<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import {
    index as organizationsIndex,
    update,
} from '@/routes/admin/organizations';
import { index as organizationUsersIndex } from '@/routes/admin/organizations/users';

type OrganizationPayload = {
    id: number;
    name: string;
    slug: string;
    billing_email: string | null;
    subscription_plan: string | null;
    is_active: boolean;
    users_count: number;
};

const props = defineProps<{
    organization: OrganizationPayload;
}>();

const { t } = useTranslations();

const form = useForm({
    name: props.organization.name,
    slug: props.organization.slug,
    billing_email: props.organization.billing_email ?? '',
    subscription_plan: props.organization.subscription_plan ?? '',
    is_active: props.organization.is_active,
});

const submit = () => {
    form.put(update(props.organization.id).url);
};
</script>

<template>
    <Head :title="t('admin.organizations.edit_title')" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">
                {{ t('admin.organizations.edit_title') }}
            </h1>
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link
                        :href="organizationUsersIndex(props.organization.id)"
                        >{{ t('nav.users') }}</Link
                    >
                </Button>
                <Button as-child variant="outline">
                    <Link :href="organizationsIndex()">{{
                        t('common.back')
                    }}</Link>
                </Button>
            </div>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">{{ t('common.name') }}</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">{{ t('admin.organizations.slug') }}</Label>
                <Input id="slug" v-model="form.slug" required />
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

            <p class="text-sm text-muted-foreground">
                {{ t('admin.organizations.users_count') }}:
                {{ props.organization.users_count }}
            </p>

            <Button type="submit" :disabled="form.processing">
                {{ t('common.save') }}
            </Button>
        </form>
    </div>
</template>
