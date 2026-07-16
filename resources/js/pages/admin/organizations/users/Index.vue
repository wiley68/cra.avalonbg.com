<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    edit as editOrganization,
    index as organizationsIndex,
} from '@/routes/admin/organizations';
import { create, edit } from '@/routes/admin/organizations/users';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    role_slug: string;
    must_change_password: boolean;
    is_system_admin: boolean;
};

const props = defineProps<{
    organization: OrganizationSummary;
    users: UserRow[];
}>();

const { t } = useTranslations();

const roleLabel = (slug: string): string => {
    const key = `roles.${slug}`;
    const translated = t(key);

    return translated === key ? t('common.unknown') : translated;
};
</script>

<template>
    <Head :title="t('admin.users.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    <Link
                        :href="organizationsIndex()"
                        class="hover:underline"
                        >{{ t('nav.organizations') }}</Link
                    >
                    /
                    <Link
                        :href="editOrganization(props.organization.id)"
                        class="hover:underline"
                        >{{ props.organization.name }}</Link
                    >
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('admin.users.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.users.subtitle') }}
                </p>
            </div>

            <Button as-child>
                <Link :href="create(props.organization.id)">{{
                    t('admin.users.create')
                }}</Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ t('common.name') }}</th>
                        <th class="px-4 py-3">{{ t('common.email') }}</th>
                        <th class="px-4 py-3">{{ t('common.role') }}</th>
                        <th class="px-4 py-3">{{ t('common.flags') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" class="border-t">
                        <td class="px-4 py-3">{{ user.name }}</td>
                        <td class="px-4 py-3">{{ user.email }}</td>
                        <td class="px-4 py-3">
                            {{ roleLabel(user.role_slug) }}
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="user.is_system_admin">{{
                                t('admin.users.flag_system_admin')
                            }}</span>
                            <span
                                v-if="user.must_change_password"
                                class="ml-2"
                                >{{
                                    t('admin.users.flag_force_password')
                                }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button as-child variant="ghost" size="sm">
                                <Link
                                    :href="
                                        edit({
                                            organization: props.organization.id,
                                            user: user.id,
                                        })
                                    "
                                    >{{ t('common.edit') }}</Link
                                >
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
