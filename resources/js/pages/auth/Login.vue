<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        titleKey: 'auth.login.title',
        descriptionKey: 'auth.login.description',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('auth.login.submit')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        autocomplete="off"
        data-1p-ignore
        data-lpignore="true"
        data-form-type="other"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('auth.login.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ t('auth.login.password') }}</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="4"
                    >
                        {{ t('auth.login.forgot') }}
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    :placeholder="t('auth.login.password')"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="3"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.login.submit') }}
            </Button>
        </div>
    </Form>
</template>
