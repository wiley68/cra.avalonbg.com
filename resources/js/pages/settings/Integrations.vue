<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { GitBranch, Save, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import IntegrationController from '@/actions/App/Http/Controllers/Settings/IntegrationController';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/settings/integrations';

type VcsConnection = {
    id: number;
    provider: string;
    auth_type: string;
    label: string | null;
    status: string;
    last_verified_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    connections: VcsConnection[];
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'settings.integrations.title', href: edit() },
]);

const githubForm = useForm({
    token: '',
    label: 'GitHub',
});

const disconnectId = ref<number | null>(null);
const disconnecting = ref(false);

const disconnectDialogOpen = computed({
    get: () => disconnectId.value !== null,
    set: (value: boolean) => {
        if (!value) {
            disconnectId.value = null;
        }
    },
});

const githubConnection = computed(() =>
    props.connections.find((connection) => connection.provider === 'github'),
);

const connectGithub = () => {
    githubForm.post(IntegrationController.storeGithub.url(), {
        preserveScroll: true,
        onSuccess: () => githubForm.reset('token'),
    });
};

const confirmDisconnect = () => {
    if (disconnectId.value === null) {
        return;
    }

    disconnecting.value = true;
    router.delete(IntegrationController.destroy.url(disconnectId.value), {
        preserveScroll: true,
        onFinish: () => {
            disconnecting.value = false;
            disconnectId.value = null;
        },
    });
};
</script>

<template>
    <Head :title="t('settings.integrations.title')" />

    <h1 class="sr-only">{{ t('settings.integrations.title') }}</h1>

    <div class="space-y-10">
        <Heading
            variant="small"
            :title="t('settings.integrations.heading')"
            :description="t('settings.integrations.description')"
        />

        <div v-if="githubConnection" class="space-y-4 rounded-lg border p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 font-medium">
                        <GitBranch class="h-4 w-4" />
                        {{
                            githubConnection.label ||
                            t('settings.integrations.github')
                        }}
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ t('settings.integrations.status') }}:
                        {{
                            t(
                                `settings.integrations.statuses.${githubConnection.status}`,
                            )
                        }}
                    </p>
                    <p
                        v-if="githubConnection.last_verified_at"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('settings.integrations.last_verified') }}:
                        {{
                            new Date(
                                githubConnection.last_verified_at,
                            ).toLocaleString()
                        }}
                    </p>
                </div>
                <Button
                    v-if="canManage"
                    type="button"
                    variant="destructive"
                    @click="disconnectId = githubConnection.id"
                >
                    <Trash2 class="h-4 w-4" />
                    {{ t('settings.integrations.disconnect') }}
                </Button>
            </div>
        </div>

        <div v-if="canManage" class="space-y-6">
            <Heading
                variant="small"
                :title="
                    githubConnection
                        ? t('settings.integrations.update_github_title')
                        : t('settings.integrations.connect_github_title')
                "
                :description="
                    t('settings.integrations.connect_github_description')
                "
            />

            <form class="space-y-6" @submit.prevent="connectGithub">
                <div class="grid gap-2">
                    <Label for="label">{{
                        t('settings.integrations.label')
                    }}</Label>
                    <Input
                        id="label"
                        v-model="githubForm.label"
                        class="mt-1 block w-full"
                        :placeholder="t('settings.integrations.github')"
                    />
                    <InputError :message="githubForm.errors.label" />
                </div>

                <div class="grid gap-2">
                    <Label for="token">{{
                        t('settings.integrations.token')
                    }}</Label>
                    <PasswordInput
                        id="token"
                        v-model="githubForm.token"
                        class="mt-1 block w-full"
                        autocomplete="off"
                        :placeholder="
                            t('settings.integrations.token_placeholder')
                        "
                        required
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ t('settings.integrations.token_help') }}
                    </p>
                    <InputError :message="githubForm.errors.token" />
                </div>

                <Button
                    type="submit"
                    :disabled="githubForm.processing"
                    data-test="connect-github-button"
                >
                    <Save class="h-4 w-4" />
                    {{
                        githubConnection
                            ? t('settings.integrations.update_token')
                            : t('settings.integrations.connect')
                    }}
                </Button>
            </form>
        </div>

        <p v-else-if="!githubConnection" class="text-sm text-muted-foreground">
            {{ t('settings.integrations.no_access') }}
        </p>
    </div>

    <AppAlertDialog
        v-model:open="disconnectDialogOpen"
        :title="t('settings.integrations.disconnect_confirm_title')"
        :description="t('settings.integrations.disconnect_confirm')"
        :confirm-label="t('settings.integrations.disconnect')"
        :loading="disconnecting"
        @confirm="confirmDisconnect"
    />
</template>
