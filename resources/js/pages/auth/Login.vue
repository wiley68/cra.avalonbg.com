<script setup lang="ts">
import { Form, Head, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { redirect as ssoRedirect } from '@/routes/auth/sso';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { register } from '@/routes';

defineOptions({
    layout: {
        titleKey: 'auth.login.title',
        descriptionKey: 'auth.login.description',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister?: boolean;
}>();

const { t } = useTranslations();
const page = usePage();

const ssoForm = useForm({
    email_or_slug: '',
});

const ssoError = computed(() => {
    const pageErrors = page.props.errors as Record<string, string> | undefined;

    return ssoForm.errors.email_or_slug || pageErrors?.email_or_slug;
});

const startSso = () => {
    ssoForm.post(ssoRedirect().url);
};
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

        <p v-if="canRegister" class="text-center text-sm text-muted-foreground">
            {{ t('auth.login.no_account') }}
            <TextLink :href="register()" class="underline underline-offset-4">
                {{ t('auth.login.register_link') }}
            </TextLink>
        </p>
    </Form>

    <div class="relative my-8">
        <div class="absolute inset-0 flex items-center">
            <span class="w-full border-t" />
        </div>
        <div class="relative flex justify-center text-xs uppercase">
            <span class="bg-background px-2 text-muted-foreground">
                {{ t('auth.login.or') }}
            </span>
        </div>
    </div>

    <form class="flex flex-col gap-4" @submit.prevent="startSso">
        <div class="grid gap-2">
            <Label for="email_or_slug">{{ t('auth.login.sso_label') }}</Label>
            <Input
                id="email_or_slug"
                v-model="ssoForm.email_or_slug"
                type="text"
                required
                :tabindex="5"
                autocomplete="off"
                :placeholder="t('auth.login.sso_placeholder')"
            />
            <p class="text-xs text-muted-foreground">
                {{ t('auth.login.sso_help') }}
            </p>
            <InputError :message="ssoError" />
        </div>

        <Button
            type="submit"
            variant="outline"
            class="w-full"
            :tabindex="6"
            :disabled="ssoForm.processing"
            data-test="sso-login-button"
        >
            <Spinner v-if="ssoForm.processing" />
            <KeyRound v-else class="h-4 w-4" />
            {{ t('auth.login.sso_submit') }}
        </Button>
    </form>
</template>
