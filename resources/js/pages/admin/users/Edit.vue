<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Role = {
    id: number;
    name: string;
};

type EditableUser = {
    id: number;
    name: string;
    email: string;
    role_id: number;
    must_change_password: boolean;
    is_system_admin: boolean;
};

const props = defineProps<{
    user: EditableUser;
    roles: Role[];
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    role_id: props.user.role_id,
    must_change_password: props.user.must_change_password,
    is_system_admin: props.user.is_system_admin,
});

const submit = () => {
    form.put(`/admin/users/${props.user.id}`);
};
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Edit user</h1>
            <Button as-child variant="outline"><Link href="/admin/users">Back</Link></Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" type="email" v-model="form.email" required />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="role_id">Role</Label>
                <select id="role_id" v-model="form.role_id" class="h-9 rounded-md border px-3">
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                </select>
                <InputError :message="form.errors.role_id" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <Checkbox :checked="form.must_change_password" @update:checked="form.must_change_password = Boolean($event)" />
                Force password change on first login
            </label>

            <label class="flex items-center gap-2 text-sm">
                <Checkbox :checked="form.is_system_admin" @update:checked="form.is_system_admin = Boolean($event)" />
                System administrator
            </label>

            <Button type="submit" :disabled="form.processing">Save changes</Button>
        </form>
    </div>
</template>

