<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, GitBranch, RefreshCw, Save, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import IntegrationController from '@/actions/App/Http/Controllers/Settings/IntegrationController';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/settings/integrations';

type VcsConnection = {
    id: number;
    provider: string;
    auth_type: string;
    label: string | null;
    status: string;
    sync_schedule: 'off' | 'hourly' | 'daily' | string;
    webhook_configured: boolean;
    webhook_url: string;
    last_verified_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    connections: VcsConnection[];
    canManage: boolean;
    revealed_webhook_secret?: string | null;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'settings.integrations.title', href: edit() },
]);

const githubForm = useForm({
    token: '',
    label: 'GitHub',
});

const scheduleForm = useForm({
    sync_schedule: 'off',
});

const disconnectId = ref<number | null>(null);
const disconnecting = ref(false);
const rotatingWebhook = ref(false);
const copyFeedback = ref<'url' | 'secret' | null>(null);

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

watch(
    githubConnection,
    (connection) => {
        scheduleForm.sync_schedule = connection?.sync_schedule ?? 'off';
    },
    { immediate: true },
);

const connectGithub = () => {
    githubForm.post(IntegrationController.storeGithub.url(), {
        preserveScroll: true,
        onSuccess: () => githubForm.reset('token'),
    });
};

const saveSyncSchedule = () => {
    if (!githubConnection.value) {
        return;
    }

    scheduleForm.put(
        IntegrationController.updateSyncSchedule.url(githubConnection.value.id),
        {
            preserveScroll: true,
        },
    );
};

const rotateWebhookSecret = () => {
    if (!githubConnection.value) {
        return;
    }

    rotatingWebhook.value = true;
    router.post(
        IntegrationController.rotateWebhookSecret.url(
            githubConnection.value.id,
        ),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                rotatingWebhook.value = false;
            },
        },
    );
};

const copyText = async (value: string, kind: 'url' | 'secret') => {
    try {
        await navigator.clipboard.writeText(value);
        copyFeedback.value = kind;
        window.setTimeout(() => {
            if (copyFeedback.value === kind) {
                copyFeedback.value = null;
            }
        }, 2000);
    } catch {
        copyFeedback.value = null;
    }
};

const copyWebhookUrl = () => {
    const connection = githubConnection.value;
    if (!connection) {
        return;
    }

    void copyText(connection.webhook_url, 'url');
};

const copyRevealedWebhookSecret = () => {
    const secret = props.revealed_webhook_secret;
    if (!secret) {
        return;
    }

    void copyText(secret, 'secret');
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

            <form
                v-if="canManage"
                class="space-y-3 border-t pt-4"
                @submit.prevent="saveSyncSchedule"
            >
                <div class="grid gap-2">
                    <Label for="sync_schedule">{{
                        t('settings.integrations.sync_schedule')
                    }}</Label>
                    <Select
                        :model-value="scheduleForm.sync_schedule"
                        @update:model-value="
                            (value) => {
                                if (typeof value === 'string') {
                                    scheduleForm.sync_schedule = value;
                                }
                            }
                        "
                    >
                        <SelectTrigger
                            id="sync_schedule"
                            class="w-full max-w-xs"
                            data-test="sync-schedule-select"
                        >
                            <SelectValue
                                :placeholder="
                                    t(
                                        'settings.integrations.sync_schedule_placeholder',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="off">
                                {{
                                    t(
                                        'settings.integrations.sync_schedules.off',
                                    )
                                }}
                            </SelectItem>
                            <SelectItem value="hourly">
                                {{
                                    t(
                                        'settings.integrations.sync_schedules.hourly',
                                    )
                                }}
                            </SelectItem>
                            <SelectItem value="daily">
                                {{
                                    t(
                                        'settings.integrations.sync_schedules.daily',
                                    )
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        {{ t('settings.integrations.sync_schedule_help') }}
                    </p>
                    <InputError :message="scheduleForm.errors.sync_schedule" />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="scheduleForm.processing"
                    data-test="save-sync-schedule-button"
                >
                    <Save class="h-4 w-4" />
                    {{ t('settings.integrations.save_sync_schedule') }}
                </Button>
            </form>
            <p v-else class="border-t pt-4 text-sm text-muted-foreground">
                {{ t('settings.integrations.sync_schedule') }}:
                {{
                    t(
                        `settings.integrations.sync_schedules.${githubConnection.sync_schedule}`,
                    )
                }}
            </p>

            <div class="space-y-3 border-t pt-4">
                <h3 class="text-sm font-medium">
                    {{ t('settings.integrations.webhook_title') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ t('settings.integrations.webhook_help') }}
                </p>
                <div class="grid gap-2">
                    <Label>{{ t('settings.integrations.webhook_url') }}</Label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Input
                            :model-value="githubConnection.webhook_url"
                            readonly
                            class="font-mono text-xs"
                            data-test="webhook-url"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="copyWebhookUrl"
                        >
                            <Copy class="h-4 w-4" />
                            {{
                                copyFeedback === 'url'
                                    ? t('settings.integrations.copied')
                                    : t('settings.integrations.copy')
                            }}
                        </Button>
                    </div>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('settings.integrations.webhook_status') }}:
                    {{
                        githubConnection.webhook_configured
                            ? t('settings.integrations.webhook_configured')
                            : t('settings.integrations.webhook_not_configured')
                    }}
                </p>
                <div
                    v-if="revealed_webhook_secret"
                    class="grid gap-2 rounded-md border border-dashed p-3"
                >
                    <Label>{{
                        t('settings.integrations.webhook_secret_once')
                    }}</Label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Input
                            :model-value="revealed_webhook_secret"
                            readonly
                            class="font-mono text-xs"
                            data-test="webhook-secret-revealed"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="copyRevealedWebhookSecret"
                        >
                            <Copy class="h-4 w-4" />
                            {{
                                copyFeedback === 'secret'
                                    ? t('settings.integrations.copied')
                                    : t('settings.integrations.copy')
                            }}
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{
                            t('settings.integrations.webhook_secret_once_help')
                        }}
                    </p>
                </div>
                <Button
                    v-if="canManage"
                    type="button"
                    variant="outline"
                    :disabled="rotatingWebhook"
                    data-test="rotate-webhook-secret-button"
                    @click="rotateWebhookSecret"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="{ 'animate-spin': rotatingWebhook }"
                    />
                    {{
                        githubConnection.webhook_configured
                            ? t('settings.integrations.rotate_webhook_secret')
                            : t('settings.integrations.generate_webhook_secret')
                    }}
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
