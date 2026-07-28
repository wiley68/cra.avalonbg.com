<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, Save, Trash2, Users } from '@lucide/vue';
import { ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import HeaderActionButton from '@/components/HeaderActionButton.vue';
import InputError from '@/components/InputError.vue';
import PageFormHeader from '@/components/PageFormHeader.vue';
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
    billing as organizationsBilling,
    destroy,
    edit as organizationsEdit,
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
    locale: string;
    users_count: number;
};

type SubscriptionPlanOption = {
    value: string;
    max_products: number | null;
    monthly_price_eur: number;
    yearly_price_eur: number | null;
};

const props = defineProps<{
    organization: OrganizationPayload;
    subscriptionPlans: SubscriptionPlanOption[];
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.organizations', href: organizationsIndex() },
    {
        title: props.organization.name,
        href: organizationsEdit(props.organization.id),
    },
]);
const showDeleteDialog = ref(false);
const deleting = ref(false);

const form = useForm({
    name: props.organization.name,
    slug: props.organization.slug,
    billing_email: props.organization.billing_email ?? '',
    subscription_plan: props.organization.subscription_plan ?? 'free',
    is_active: Boolean(props.organization.is_active),
    locale: props.organization.locale || 'en',
});

const submit = () => {
    form.put(update(props.organization.id).url);
};

const planLabel = (value: string): string =>
    t(`admin.organizations.plans.${value}`);

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
        <PageFormHeader>
            <h1 class="text-xl font-semibold">
                {{ t('admin.organizations.edit_title') }}
            </h1>
            <template #actions>
                <HeaderActionButton
                    is-back
                    :label="t('common.back')"
                    :href="organizationsIndex()"
                >
                    <ArrowLeft class="h-4 w-4" />
                </HeaderActionButton>
                <HeaderActionButton
                    :label="t('admin.organizations.billing_page_title')"
                    :href="organizationsBilling(props.organization.id)"
                >
                    <Banknote class="h-4 w-4" />
                </HeaderActionButton>
                <HeaderActionButton
                    :label="t('nav.users')"
                    :href="organizationUsersIndex(props.organization.id)"
                >
                    <Users class="h-4 w-4" />
                </HeaderActionButton>
            </template>
        </PageFormHeader>

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
                <Select
                    :model-value="form.subscription_plan || undefined"
                    @update:model-value="
                        (value) => {
                            if (typeof value === 'string') {
                                form.subscription_plan = value;
                            }
                        }
                    "
                >
                    <SelectTrigger id="subscription_plan" class="w-full">
                        <SelectValue
                            :placeholder="
                                t('admin.organizations.subscription_plan')
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="plan in props.subscriptionPlans"
                            :key="plan.value"
                            :value="plan.value"
                        >
                            {{ planLabel(plan.value) }}
                            <span class="text-muted-foreground">
                                ({{
                                    plan.max_products === null
                                        ? '∞'
                                        : plan.max_products
                                }})
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.organizations.subscription_plan_help') }}
                </p>
                <InputError :message="form.errors.subscription_plan" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{
                    t('admin.organizations.locale')
                }}</Label>
                <select
                    id="locale"
                    v-model="form.locale"
                    class="h-9 rounded-md border bg-background px-3"
                    required
                >
                    <option value="en">
                        {{ t('admin.organizations.locale_en') }}
                    </option>
                    <option value="bg">
                        {{ t('admin.organizations.locale_bg') }}
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    {{ t('admin.organizations.locale_help_edit') }}
                </p>
                <InputError :message="form.errors.locale" />
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
                variant="outline"
                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
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
