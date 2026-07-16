<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

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

defineOptions({
    layout: {
        titleKey: 'auth.force_password.title',
        descriptionKey: 'auth.force_password.description',
    },
});
</script>

<template>
    <Head :title="t('auth.force_password.head_title')" />

    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="password">{{
                t('auth.force_password.new_password')
            }}</Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                required
                autocomplete="new-password"
                :placeholder="t('auth.force_password.placeholder_new')"
            />
            <InputError :message="form.errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">{{
                t('auth.force_password.confirm_password')
            }}</Label>
            <PasswordInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                required
                autocomplete="new-password"
                :placeholder="t('auth.force_password.placeholder_confirm')"
            />
        </div>

        <Button type="submit" :disabled="form.processing" class="w-full">
            {{ t('auth.force_password.submit') }}
        </Button>
    </form>
</template>
