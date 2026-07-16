<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/auth/force-password-change', {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Change password" />

    <div class="mx-auto flex min-h-screen w-full max-w-md items-center px-6">
        <form class="w-full space-y-6" @submit.prevent="submit">
            <div>
                <h1 class="text-xl font-semibold">Change your password</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Your account requires a password change before continuing.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="New password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
            </div>

            <Button type="submit" :disabled="form.processing" class="w-full">
                Update password
            </Button>
        </form>
    </div>
</template>

