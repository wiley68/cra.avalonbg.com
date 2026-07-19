<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { destroy } from '@/routes/settings/organization';

const props = defineProps<{
    organization: {
        id: number;
        name: string;
        slug: string;
    };
}>();

const { t } = useTranslations();
const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div
        class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
    >
        <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
            <p class="font-medium">
                {{ t('settings.delete_organization.warning_title') }}
            </p>
            <p class="text-sm">
                {{ t('settings.delete_organization.warning_body') }}
            </p>
        </div>

        <Dialog>
            <DialogTrigger as-child>
                <Button
                    variant="destructive"
                    data-test="delete-organization-button"
                >
                    {{ t('settings.delete_organization.button') }}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    :action="destroy()"
                    reset-on-success
                    @error="() => passwordInput?.focus()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="space-y-6"
                    v-slot="{ errors, processing, reset, clearErrors }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{ t('settings.delete_organization.dialog_title') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                t(
                                    'settings.delete_organization.dialog_description',
                                    { name: props.organization.name },
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="organization_confirmation">
                            {{
                                t(
                                    'settings.delete_organization.confirmation_label',
                                    { name: props.organization.name },
                                )
                            }}
                        </Label>
                        <Input
                            id="organization_confirmation"
                            name="confirmation"
                            autocomplete="off"
                            :placeholder="props.organization.name"
                        />
                        <InputError :message="errors.confirmation" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="organization_password">
                            {{ t('auth.login.password') }}
                        </Label>
                        <PasswordInput
                            id="organization_password"
                            name="password"
                            ref="passwordInput"
                            :placeholder="t('auth.login.password')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                "
                            >
                                {{ t('common.cancel') }}
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-organization-button"
                        >
                            {{ t('settings.delete_organization.confirm') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
