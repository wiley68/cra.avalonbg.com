<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/password/confirm';

defineProps<{
    redirect?: string | null;
}>();

defineOptions({
    layout: {
        titleKey: 'auth.confirm_password.title',
        descriptionKey: 'auth.confirm_password.description',
    },
});

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('auth.confirm_password.head_title')" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <input
            v-if="redirect"
            type="hidden"
            name="redirect"
            :value="redirect"
        />

        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{
                    t('auth.confirm_password.password')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.confirm_password.submit') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
