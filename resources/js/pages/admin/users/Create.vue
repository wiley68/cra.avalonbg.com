<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Role = {
    id: number;
    name: string;
};

const props = defineProps<{
    roles: Role[];
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: props.roles[0]?.id ?? 0,
    must_change_password: true,
});

const submit = () => {
    form.post('/admin/users');
};
</script>

<template>
    <Head title="Create user" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Create user</h1>
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
                <Label for="password">Temporary password</Label>
                <PasswordInput id="password" v-model="form.password" required />
                <InputError :message="form.errors.password" />
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

            <Button type="submit" :disabled="form.processing">Create</Button>
        </form>
    </div>
</template>

