<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { destroy, index as usersIndex, update } from '@/routes/users';

type Role = {
    id: number;
    name: string;
    slug: string;
};

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type EditableUser = {
    id: number;
    name: string;
    email: string;
    role_id: number;
    must_change_password: boolean;
};

const props = defineProps<{
    organization: OrganizationSummary;
    user: EditableUser;
    roles: Role[];
}>();

const { t } = useTranslations();

const showDeleteDialog = ref(false);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    role_id: props.user.role_id,
    must_change_password: Boolean(props.user.must_change_password),
});

const submit = () => {
    form.put(update(props.user.id).url);
};

const confirmDelete = () => {
    showDeleteDialog.value = false;
    router.delete(destroy(props.user.id).url);
};

const roleLabel = (slug: string): string => {
    const key = `roles.${slug}`;
    const translated = t(key);

    return translated === key ? slug : translated;
};
</script>

<template>
    <Head :title="t('users.edit_title')" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.organization.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('users.edit_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="usersIndex()">
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
                <Label for="email">{{ t('common.email') }}</Label>
                <Input id="email" type="email" v-model="form.email" required />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="role_id">{{ t('common.role') }}</Label>
                <select
                    id="role_id"
                    v-model="form.role_id"
                    class="h-9 rounded-md border bg-background px-3"
                >
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                    >
                        {{ roleLabel(role.slug) }}
                    </option>
                </select>
                <InputError :message="form.errors.role_id" />
            </div>

            <div class="flex items-center gap-3">
                <Switch
                    id="must_change_password"
                    v-model="form.must_change_password"
                    class="cursor-pointer"
                />
                <Label for="must_change_password" class="cursor-pointer">
                    {{ t('admin.users.force_password') }}
                </Label>
            </div>

            <div class="flex items-center justify-between gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="showDeleteDialog = true"
                >
                    <Trash2 class="h-4 w-4" />
                    {{ t('common.delete') }}
                </Button>
            </div>
        </form>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('users.confirm_delete')"
            @confirm="confirmDelete"
        />
    </div>
</template>
