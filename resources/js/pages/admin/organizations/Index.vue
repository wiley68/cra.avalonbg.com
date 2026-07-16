<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { create, edit } from '@/routes/admin/organizations';
import { index as organizationUsersIndex } from '@/routes/admin/organizations/users';

type OrganizationRow = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    billing_email: string | null;
    subscription_plan: string | null;
    users_count: number;
};

defineProps<{
    organizations: OrganizationRow[];
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('admin.organizations.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('admin.organizations.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.organizations.subtitle') }}
                </p>
            </div>

            <Button as-child>
                <Link :href="create()">{{
                    t('admin.organizations.create')
                }}</Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ t('common.name') }}</th>
                        <th class="px-4 py-3">
                            {{ t('admin.organizations.slug') }}
                        </th>
                        <th class="px-4 py-3">
                            {{ t('admin.organizations.status') }}
                        </th>
                        <th class="px-4 py-3">
                            {{ t('admin.organizations.users_count') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="organization in organizations"
                        :key="organization.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">{{ organization.name }}</td>
                        <td class="px-4 py-3">{{ organization.slug }}</td>
                        <td class="px-4 py-3">
                            {{
                                organization.is_active
                                    ? t('admin.organizations.active')
                                    : t('admin.organizations.inactive')
                            }}
                        </td>
                        <td class="px-4 py-3">
                            {{ organization.users_count }}
                        </td>
                        <td class="space-x-2 px-4 py-3 text-right">
                            <Button as-child variant="ghost" size="sm">
                                <Link
                                    :href="
                                        organizationUsersIndex(organization.id)
                                    "
                                    >{{ t('nav.users') }}</Link
                                >
                            </Button>
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="edit(organization.id)">{{
                                    t('common.edit')
                                }}</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
