<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteOrganization from '@/components/DeleteOrganization.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const props = defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
    canDeleteOrganization?: boolean;
    deletableOrganization?: {
        id: number;
        name: string;
        slug: string;
    } | null;
}>();

const page = usePage();
const { t } = useTranslations();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="t('settings.profile_title')" />

    <h1 class="sr-only">{{ t('settings.profile_title') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('settings.profile_heading')"
            :description="t('settings.profile_description')"
        />

        <Form
            :action="ProfileController.update()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('common.name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user?.name"
                    required
                    autocomplete="name"
                    :placeholder="t('common.name')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('auth.login.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user?.email"
                    required
                    autocomplete="username"
                    :placeholder="t('auth.login.email')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="props.mustVerifyEmail && !user?.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ t('settings.email_unverified') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{ t('settings.resend_verification') }}
                    </Link>
                </p>

                <div
                    v-if="props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ t('settings.verification_sent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ t('common.save') }}
                </Button>
            </div>
        </Form>
    </div>

    <div
        v-if="canDeleteOrganization && deletableOrganization"
        class="mt-8 space-y-6"
    >
        <Heading
            variant="small"
            :title="t('settings.delete_organization.title')"
            :description="t('settings.delete_organization.description')"
        />
        <DeleteOrganization :organization="deletableOrganization" />
    </div>

    <div class="mt-8">
        <DeleteUser />
    </div>
</template>
