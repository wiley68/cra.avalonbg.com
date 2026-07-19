<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Trash2, Users } from '@lucide/vue';
import { ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    destroy,
    index as organizationsIndex,
    update,
} from '@/routes/admin/organizations';
import { index as organizationUsersIndex } from '@/routes/admin/organizations/users';
import { edit as organizationsEdit } from '@/routes/admin/organizations';

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

usePageBreadcrumbs(() => [
    { titleKey: 'nav.organizations', href: organizationsIndex() },
    { title: props.organization.name, href: organizationsEdit(props.organization.id) },
]);
const showDeleteDialog = ref(false);
const deleting = ref(false);

const form = useForm({
    name: props.organization.name,
    slug: props.organization.slug,
    billing_email: props.organization.billing_email ?? '',
    subscription_plan: props.organization.subscription_plan ?? '',
    is_active: Boolean(props.organization.is_active),
});

const submit = () => {
    form.put(update(props.organization.id).url);
};

const confirmDelete = () => {
    deleting.value = true;
    showDeleteDialog.value = false;

    router.delete(destroy(props.organization.id).url, {
        onFinish: () => {
            deleting.value = false;
        },
    });
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
                        class="inline-flex items-center gap-2"
                    >
                        <Users class="h-4 w-4" />
                        {{ t('nav.users') }}
                    </Link>
                </Button>
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
            <InputError :message="form.errors.is_active" />

            <p class="text-sm text-muted-foreground">
                {{ t('admin.organizations.users_count') }}:
                {{ props.organization.users_count }}
            </p>

            <Button type="submit" :disabled="form.processing">
                <Save class="h-4 w-4" />
                {{ t('common.save') }}
            </Button>
        </form>

        <section class="space-y-3 rounded-lg border border-destructive/40 p-6">
            <h2 class="text-sm font-semibold text-destructive">
                {{ t('admin.organizations.delete') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ t('admin.organizations.confirm_delete') }}
            </p>
            <Button
                type="button"
                variant="destructive"
                :disabled="deleting"
                @click="showDeleteDialog = true"
            >
                <Trash2 class="h-4 w-4" />
                {{ t('admin.organizations.delete') }}
            </Button>
        </section>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('admin.organizations.confirm_delete_title')"
            :description="t('admin.organizations.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="showDeleteDialog = false"
        />
    </div>
</template>
