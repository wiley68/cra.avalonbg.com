<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    Copy,
    GitBranch,
    RefreshCw,
    Save,
    Shield,
    Trash2,
} from '@lucide/vue';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as integrationHealthIndex } from '@/routes/integrations/health';
import { edit } from '@/routes/settings/integrations';

type VcsConnection = {
    id: number;
    provider: string;
    auth_type: string;
    label: string | null;
    status: string;
    sync_schedule: 'off' | 'hourly' | 'daily' | string;
    github_app_id?: string | null;
    github_installation_id?: string | null;
    has_github_private_key?: boolean;
    webhook_configured: boolean;
    webhook_url: string;
    last_verified_at: string | null;
    created_at: string | null;
};

type OrganizationIntegrationRow = {
    id: number;
    provider: string;
    category: string;
    auth_type: string;
    label: string | null;
    status: string;
    sync_schedule: 'off' | 'hourly' | 'daily' | string;
    base_url: string | null;
    email: string | null;
    organization?: string | null;
    last_verified_at: string | null;
    created_at: string | null;
};

type DisconnectTarget = {
    type: 'vcs' | 'integration';
    id: number;
};

type OpsQueueHint = {
    level: 'warn' | 'fail';
    code: string;
};

const props = defineProps<{
    connections: VcsConnection[];
    integrations: OrganizationIntegrationRow[];
    canManage: boolean;
    opsQueueHint: OpsQueueHint | null;
    revealed_webhook_secret?: string | null;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'settings.integrations.title', href: edit() },
]);

const opsHintMessage = computed(() => {
    if (!props.opsQueueHint) {
        return null;
    }

    const key = `integrations.health.ops_hints.${props.opsQueueHint.code}`;
    const translated = t(key);

    return translated === key ? props.opsQueueHint.code : translated;
});

const githubForm = useForm({
    token: '',
    label: 'GitHub',
});

const githubAppForm = useForm({
    github_app_id: '',
    github_installation_id: '',
    github_private_key: '',
    label: 'GitHub App',
});

const githubAuthMethod = ref<'pat' | 'github_app'>('pat');

const gitlabForm = useForm({
    token: '',
    label: 'GitLab',
});

const jiraForm = useForm({
    base_url: '',
    email: '',
    api_token: '',
    label: 'Jira Cloud',
});

const snykForm = useForm({
    api_token: '',
    base_url: '',
    label: 'Snyk',
});

const azureDevOpsForm = useForm({
    organization: '',
    pat: '',
    base_url: '',
    label: 'Azure DevOps',
});

const sarifForm = useForm({
    label: 'SARIF / Trivy',
});

const scheduleForm = useForm({
    sync_schedule: 'off',
});

const gitlabScheduleForm = useForm({
    sync_schedule: 'off',
});

const jiraScheduleForm = useForm({
    sync_schedule: 'off',
});

const snykScheduleForm = useForm({
    sync_schedule: 'off',
});

const azureDevOpsScheduleForm = useForm({
    sync_schedule: 'off',
});

const disconnectTarget = ref<DisconnectTarget | null>(null);
const disconnecting = ref(false);
const rotatingWebhook = ref(false);
const copyFeedback = ref<'url' | 'secret' | null>(null);

const disconnectDialogOpen = computed({
    get: () => disconnectTarget.value !== null,
    set: (value: boolean) => {
        if (!value) {
            disconnectTarget.value = null;
        }
    },
});

const githubConnection = computed(() =>
    props.connections.find((connection) => connection.provider === 'github'),
);

const gitlabConnection = computed(() =>
    props.connections.find((connection) => connection.provider === 'gitlab'),
);

const jiraIntegration = computed(() =>
    props.integrations.find((integration) => integration.provider === 'jira'),
);

const snykIntegration = computed(() =>
    props.integrations.find((integration) => integration.provider === 'snyk'),
);

const azureDevOpsIntegration = computed(() =>
    props.integrations.find(
        (integration) => integration.provider === 'azure_devops',
    ),
);

const sarifIntegration = computed(() =>
    props.integrations.find((integration) => integration.provider === 'sarif'),
);

const defaultTab = ():
    'github' | 'gitlab' | 'jira' | 'azure-devops' | 'snyk' | 'sarif' => {
    if (props.revealed_webhook_secret) {
        return 'github';
    }

    if (
        !githubConnection.value &&
        !gitlabConnection.value &&
        !jiraIntegration.value &&
        !azureDevOpsIntegration.value &&
        !snykIntegration.value &&
        sarifIntegration.value
    ) {
        return 'sarif';
    }

    if (
        !githubConnection.value &&
        !gitlabConnection.value &&
        !jiraIntegration.value &&
        !azureDevOpsIntegration.value &&
        snykIntegration.value
    ) {
        return 'snyk';
    }

    if (
        !githubConnection.value &&
        !gitlabConnection.value &&
        jiraIntegration.value
    ) {
        return 'jira';
    }

    if (!githubConnection.value && gitlabConnection.value) {
        return 'gitlab';
    }

    return 'github';
};

const activeTab = ref<
    'github' | 'gitlab' | 'jira' | 'azure-devops' | 'snyk' | 'sarif'
>(defaultTab());

watch(
    () => props.revealed_webhook_secret,
    (secret) => {
        if (secret) {
            activeTab.value = 'github';
        }
    },
);

watch(
    githubConnection,
    (connection) => {
        scheduleForm.sync_schedule = connection?.sync_schedule ?? 'off';

        if (connection?.auth_type === 'github_app') {
            githubAuthMethod.value = 'github_app';
            githubAppForm.github_app_id = connection.github_app_id ?? '';
            githubAppForm.github_installation_id =
                connection.github_installation_id ?? '';
            githubAppForm.label = connection.label ?? 'GitHub App';
        } else if (connection) {
            githubAuthMethod.value = 'pat';
            githubForm.label = connection.label ?? 'GitHub';
        }
    },
    { immediate: true },
);

watch(
    gitlabConnection,
    (connection) => {
        gitlabScheduleForm.sync_schedule = connection?.sync_schedule ?? 'off';
    },
    { immediate: true },
);

watch(
    jiraIntegration,
    (integration) => {
        if (!integration) {
            return;
        }

        jiraForm.base_url = integration.base_url ?? '';
        jiraForm.email = integration.email ?? '';
        jiraForm.label = integration.label ?? 'Jira Cloud';
        jiraScheduleForm.sync_schedule = integration.sync_schedule ?? 'off';
    },
    { immediate: true },
);

watch(
    snykIntegration,
    (integration) => {
        if (!integration) {
            return;
        }

        snykForm.base_url = integration.base_url ?? '';
        snykForm.label = integration.label ?? 'Snyk';
        snykScheduleForm.sync_schedule = integration.sync_schedule ?? 'off';
    },
    { immediate: true },
);

watch(
    azureDevOpsIntegration,
    (integration) => {
        if (!integration) {
            return;
        }

        azureDevOpsForm.organization = integration.organization ?? '';
        azureDevOpsForm.base_url = integration.base_url ?? '';
        azureDevOpsForm.label = integration.label ?? 'Azure DevOps';
        azureDevOpsScheduleForm.sync_schedule =
            integration.sync_schedule ?? 'off';
    },
    { immediate: true },
);

const connectGithub = () => {
    githubForm.post(IntegrationController.storeGithub.url(), {
        preserveScroll: true,
        onSuccess: () => githubForm.reset('token'),
    });
};

const connectGithubApp = () => {
    githubAppForm.post(IntegrationController.storeGithubApp.url(), {
        preserveScroll: true,
        onSuccess: () => githubAppForm.reset('github_private_key'),
    });
};

const connectGitlab = () => {
    gitlabForm.post(IntegrationController.storeGitlab.url(), {
        preserveScroll: true,
        onSuccess: () => gitlabForm.reset('token'),
    });
};

const connectJira = () => {
    jiraForm.post(IntegrationController.storeJira.url(), {
        preserveScroll: true,
        onSuccess: () => jiraForm.reset('api_token'),
    });
};

const connectSnyk = () => {
    snykForm.post(IntegrationController.storeSnyk.url(), {
        preserveScroll: true,
        onSuccess: () => snykForm.reset('api_token'),
    });
};

const connectAzureDevOps = () => {
    azureDevOpsForm.post(IntegrationController.storeAzureDevOps.url(), {
        preserveScroll: true,
        onSuccess: () => azureDevOpsForm.reset('pat'),
    });
};

const connectSarif = () => {
    sarifForm.post(IntegrationController.storeSarif.url(), {
        preserveScroll: true,
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

const saveGitlabSyncSchedule = () => {
    if (!gitlabConnection.value) {
        return;
    }

    gitlabScheduleForm.put(
        IntegrationController.updateSyncSchedule.url(gitlabConnection.value.id),
        {
            preserveScroll: true,
        },
    );
};

const saveJiraSyncSchedule = () => {
    if (!jiraIntegration.value) {
        return;
    }

    jiraScheduleForm.put(
        IntegrationController.updateIntegrationSyncSchedule.url(
            jiraIntegration.value.id,
        ),
        {
            preserveScroll: true,
        },
    );
};

const saveSnykSyncSchedule = () => {
    if (!snykIntegration.value) {
        return;
    }

    snykScheduleForm.put(
        IntegrationController.updateIntegrationSyncSchedule.url(
            snykIntegration.value.id,
        ),
        {
            preserveScroll: true,
        },
    );
};

const saveAzureDevOpsSyncSchedule = () => {
    if (!azureDevOpsIntegration.value) {
        return;
    }

    azureDevOpsScheduleForm.put(
        IntegrationController.updateIntegrationSyncSchedule.url(
            azureDevOpsIntegration.value.id,
        ),
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
    if (disconnectTarget.value === null) {
        return;
    }

    const target = disconnectTarget.value;
    const url =
        target.type === 'integration'
            ? IntegrationController.destroyIntegration.url(target.id)
            : IntegrationController.destroy.url(target.id);

    disconnecting.value = true;
    router.delete(url, {
        preserveScroll: true,
        onFinish: () => {
            disconnecting.value = false;
            disconnectTarget.value = null;
        },
    });
};
</script>

<template>
    <Head :title="t('settings.integrations.title')" />

    <h1 class="sr-only">{{ t('settings.integrations.title') }}</h1>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                variant="small"
                :title="t('settings.integrations.heading')"
                :description="t('settings.integrations.description')"
            />
            <Button variant="outline" size="sm" as-child>
                <Link
                    :href="
                        integrationHealthIndex({
                            query: { from: edit().url },
                        })
                    "
                >
                    <Activity class="h-4 w-4" />
                    {{ t('settings.integrations.health_link') }}
                </Link>
            </Button>
        </div>

        <div
            v-if="opsHintMessage"
            class="rounded-lg border px-4 py-3 text-sm"
            :class="
                props.opsQueueHint?.level === 'fail'
                    ? 'border-destructive/30 bg-destructive/5 text-destructive'
                    : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100'
            "
        >
            {{ opsHintMessage }}
        </div>

        <Tabs
            v-if="
                canManage ||
                githubConnection ||
                gitlabConnection ||
                jiraIntegration ||
                azureDevOpsIntegration ||
                snykIntegration ||
                sarifIntegration
            "
            v-model="activeTab"
            class="gap-6"
        >
            <TabsList class="w-full sm:w-fit" data-test="integrations-tabs">
                <TabsTrigger value="github" class="flex-1 sm:flex-none">
                    {{ t('settings.integrations.github') }}
                </TabsTrigger>
                <TabsTrigger value="gitlab" class="flex-1 sm:flex-none">
                    {{ t('settings.integrations.gitlab') }}
                </TabsTrigger>
                <TabsTrigger value="jira" class="flex-1 sm:flex-none">
                    {{ t('settings.integrations.jira') }}
                </TabsTrigger>
                <TabsTrigger value="azure-devops" class="flex-1 sm:flex-none">
                    {{ t('settings.integrations.azure_devops') }}
                </TabsTrigger>
                <TabsTrigger value="snyk" class="flex-1 sm:flex-none">
                    {{ t('settings.integrations.snyk') }}
                </TabsTrigger>
                <TabsTrigger value="sarif" class="flex-1 sm:flex-none">
                    {{ t('settings.integrations.sarif') }}
                </TabsTrigger>
            </TabsList>

            <TabsContent value="github" class="space-y-6">
                <div
                    v-if="githubConnection"
                    class="space-y-4 rounded-lg border p-4"
                >
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
                            <p class="text-sm text-muted-foreground">
                                {{
                                    t('settings.integrations.auth_type_label')
                                }}:
                                {{
                                    t(
                                        `settings.integrations.auth_methods.${githubConnection.auth_type}`,
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
                            variant="outline"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="
                                disconnectTarget = {
                                    type: 'vcs',
                                    id: githubConnection.id,
                                }
                            "
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
                                {{
                                    t(
                                        'settings.integrations.sync_schedule_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="scheduleForm.errors.sync_schedule"
                            />
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
                    <p
                        v-else
                        class="border-t pt-4 text-sm text-muted-foreground"
                    >
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
                            <Label>{{
                                t('settings.integrations.webhook_url')
                            }}</Label>
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
                                    ? t(
                                          'settings.integrations.webhook_configured',
                                      )
                                    : t(
                                          'settings.integrations.webhook_not_configured',
                                      )
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
                                    t(
                                        'settings.integrations.webhook_secret_once_help',
                                    )
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
                                    ? t(
                                          'settings.integrations.rotate_webhook_secret',
                                      )
                                    : t(
                                          'settings.integrations.generate_webhook_secret',
                                      )
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
                                : t(
                                      'settings.integrations.connect_github_title',
                                  )
                        "
                        :description="
                            t(
                                'settings.integrations.connect_github_description',
                            )
                        "
                    />

                    <div class="grid gap-2">
                        <Label for="github_auth_method">{{
                            t('settings.integrations.auth_method')
                        }}</Label>
                        <Select
                            :model-value="githubAuthMethod"
                            @update:model-value="
                                (value) => {
                                    if (
                                        value === 'pat' ||
                                        value === 'github_app'
                                    ) {
                                        githubAuthMethod = value;
                                    }
                                }
                            "
                        >
                            <SelectTrigger
                                id="github_auth_method"
                                class="w-full max-w-xs"
                                data-test="github-auth-method-select"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pat">
                                    {{
                                        t(
                                            'settings.integrations.auth_methods.pat',
                                        )
                                    }}
                                </SelectItem>
                                <SelectItem value="github_app">
                                    {{
                                        t(
                                            'settings.integrations.auth_methods.github_app',
                                        )
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <form
                        v-if="githubAuthMethod === 'pat'"
                        class="space-y-6"
                        @submit.prevent="connectGithub"
                    >
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
                                githubConnection?.auth_type === 'pat'
                                    ? t('settings.integrations.update_token')
                                    : t('settings.integrations.connect')
                            }}
                        </Button>
                    </form>

                    <form
                        v-else
                        class="space-y-6"
                        @submit.prevent="connectGithubApp"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ t('settings.integrations.github_app_help') }}
                        </p>

                        <div class="grid gap-2">
                            <Label for="github_app_label">{{
                                t('settings.integrations.label')
                            }}</Label>
                            <Input
                                id="github_app_label"
                                v-model="githubAppForm.label"
                                class="mt-1 block w-full"
                                :placeholder="
                                    t(
                                        'settings.integrations.auth_methods.github_app',
                                    )
                                "
                            />
                            <InputError :message="githubAppForm.errors.label" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="github_app_id">{{
                                t('settings.integrations.github_app_id')
                            }}</Label>
                            <Input
                                id="github_app_id"
                                v-model="githubAppForm.github_app_id"
                                class="mt-1 block w-full"
                                required
                                data-test="github-app-id"
                            />
                            <p class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.github_app_id_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="githubAppForm.errors.github_app_id"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="github_installation_id">{{
                                t(
                                    'settings.integrations.github_installation_id',
                                )
                            }}</Label>
                            <Input
                                id="github_installation_id"
                                v-model="githubAppForm.github_installation_id"
                                class="mt-1 block w-full"
                                required
                                data-test="github-installation-id"
                            />
                            <p class="text-sm text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.github_installation_id_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="
                                    githubAppForm.errors.github_installation_id
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="github_private_key">{{
                                t('settings.integrations.github_private_key')
                            }}</Label>
                            <textarea
                                id="github_private_key"
                                v-model="githubAppForm.github_private_key"
                                rows="6"
                                class="mt-1 flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :placeholder="
                                    t(
                                        'settings.integrations.github_private_key_placeholder',
                                    )
                                "
                                :required="
                                    !(
                                        githubConnection?.auth_type ===
                                            'github_app' &&
                                        githubConnection.has_github_private_key
                                    )
                                "
                                data-test="github-private-key"
                            />
                            <p class="text-sm text-muted-foreground">
                                {{
                                    githubConnection?.auth_type ===
                                        'github_app' &&
                                    githubConnection.has_github_private_key
                                        ? t(
                                              'settings.integrations.github_private_key_optional_help',
                                          )
                                        : t(
                                              'settings.integrations.github_private_key_help',
                                          )
                                }}
                            </p>
                            <InputError
                                :message="
                                    githubAppForm.errors.github_private_key
                                "
                            />
                        </div>

                        <Button
                            type="submit"
                            :disabled="githubAppForm.processing"
                            data-test="connect-github-app-button"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                githubConnection?.auth_type === 'github_app'
                                    ? t(
                                          'settings.integrations.update_github_app',
                                      )
                                    : t(
                                          'settings.integrations.connect_github_app',
                                      )
                            }}
                        </Button>
                    </form>
                </div>

                <p
                    v-else-if="!githubConnection"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('settings.integrations.provider_not_connected') }}
                </p>
            </TabsContent>

            <TabsContent value="gitlab" class="space-y-6">
                <div
                    v-if="gitlabConnection"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 font-medium">
                                <GitBranch class="h-4 w-4" />
                                {{
                                    gitlabConnection.label ||
                                    t('settings.integrations.gitlab')
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('settings.integrations.status') }}:
                                {{
                                    t(
                                        `settings.integrations.statuses.${gitlabConnection.status}`,
                                    )
                                }}
                            </p>
                            <p
                                v-if="gitlabConnection.last_verified_at"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.last_verified') }}:
                                {{
                                    new Date(
                                        gitlabConnection.last_verified_at,
                                    ).toLocaleString()
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="outline"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="
                                disconnectTarget = {
                                    type: 'vcs',
                                    id: gitlabConnection.id,
                                }
                            "
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ t('settings.integrations.disconnect') }}
                        </Button>
                    </div>

                    <form
                        v-if="canManage"
                        class="space-y-3 border-t pt-4"
                        @submit.prevent="saveGitlabSyncSchedule"
                    >
                        <div class="grid gap-2">
                            <Label for="gitlab_sync_schedule">{{
                                t('settings.integrations.sync_schedule')
                            }}</Label>
                            <Select
                                :model-value="gitlabScheduleForm.sync_schedule"
                                @update:model-value="
                                    (value) => {
                                        if (typeof value === 'string') {
                                            gitlabScheduleForm.sync_schedule =
                                                value;
                                        }
                                    }
                                "
                            >
                                <SelectTrigger
                                    id="gitlab_sync_schedule"
                                    class="w-full max-w-xs"
                                    data-test="gitlab-sync-schedule-select"
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
                                {{
                                    t(
                                        'settings.integrations.sync_schedule_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="
                                    gitlabScheduleForm.errors.sync_schedule
                                "
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="gitlabScheduleForm.processing"
                            data-test="save-gitlab-sync-schedule-button"
                        >
                            <Save class="h-4 w-4" />
                            {{ t('settings.integrations.save_sync_schedule') }}
                        </Button>
                    </form>
                    <p
                        v-else
                        class="border-t pt-4 text-sm text-muted-foreground"
                    >
                        {{ t('settings.integrations.sync_schedule') }}:
                        {{
                            t(
                                `settings.integrations.sync_schedules.${gitlabConnection.sync_schedule}`,
                            )
                        }}
                    </p>
                </div>

                <div v-if="canManage" class="space-y-6">
                    <Heading
                        variant="small"
                        :title="
                            gitlabConnection
                                ? t('settings.integrations.update_gitlab_title')
                                : t(
                                      'settings.integrations.connect_gitlab_title',
                                  )
                        "
                        :description="
                            t(
                                'settings.integrations.connect_gitlab_description',
                            )
                        "
                    />

                    <form class="space-y-6" @submit.prevent="connectGitlab">
                        <div class="grid gap-2">
                            <Label for="gitlab_label">{{
                                t('settings.integrations.label')
                            }}</Label>
                            <Input
                                id="gitlab_label"
                                v-model="gitlabForm.label"
                                class="mt-1 block w-full"
                                :placeholder="t('settings.integrations.gitlab')"
                            />
                            <InputError :message="gitlabForm.errors.label" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="gitlab_token">{{
                                t('settings.integrations.token')
                            }}</Label>
                            <PasswordInput
                                id="gitlab_token"
                                v-model="gitlabForm.token"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                :placeholder="
                                    t(
                                        'settings.integrations.gitlab_token_placeholder',
                                    )
                                "
                                required
                            />
                            <p class="text-sm text-muted-foreground">
                                {{
                                    t('settings.integrations.gitlab_token_help')
                                }}
                            </p>
                            <InputError :message="gitlabForm.errors.token" />
                        </div>

                        <Button
                            type="submit"
                            :disabled="gitlabForm.processing"
                            data-test="connect-gitlab-button"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                gitlabConnection
                                    ? t('settings.integrations.update_token')
                                    : t('settings.integrations.connect_gitlab')
                            }}
                        </Button>
                    </form>
                </div>

                <p
                    v-else-if="!gitlabConnection"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('settings.integrations.provider_not_connected') }}
                </p>
            </TabsContent>

            <TabsContent value="jira" class="space-y-6">
                <div
                    v-if="jiraIntegration"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 font-medium">
                                <GitBranch class="h-4 w-4" />
                                {{
                                    jiraIntegration.label ||
                                    t('settings.integrations.jira')
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('settings.integrations.status') }}:
                                {{
                                    t(
                                        `settings.integrations.statuses.${jiraIntegration.status}`,
                                    )
                                }}
                            </p>
                            <p
                                v-if="jiraIntegration.base_url"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.jira_base_url') }}:
                                {{ jiraIntegration.base_url }}
                            </p>
                            <p
                                v-if="jiraIntegration.email"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.jira_email') }}:
                                {{ jiraIntegration.email }}
                            </p>
                            <p
                                v-if="jiraIntegration.last_verified_at"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.last_verified') }}:
                                {{
                                    new Date(
                                        jiraIntegration.last_verified_at,
                                    ).toLocaleString()
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="outline"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            data-test="disconnect-jira-button"
                            @click="
                                disconnectTarget = {
                                    type: 'integration',
                                    id: jiraIntegration.id,
                                }
                            "
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ t('settings.integrations.disconnect') }}
                        </Button>
                    </div>

                    <form
                        v-if="canManage"
                        class="space-y-3 border-t pt-4"
                        @submit.prevent="saveJiraSyncSchedule"
                    >
                        <div class="grid gap-2">
                            <Label for="jira_sync_schedule">{{
                                t('settings.integrations.sync_schedule')
                            }}</Label>
                            <Select
                                :model-value="jiraScheduleForm.sync_schedule"
                                @update:model-value="
                                    (value) => {
                                        if (typeof value === 'string') {
                                            jiraScheduleForm.sync_schedule =
                                                value;
                                        }
                                    }
                                "
                            >
                                <SelectTrigger
                                    id="jira_sync_schedule"
                                    class="w-full max-w-xs"
                                    data-test="jira-sync-schedule-select"
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
                                {{
                                    t(
                                        'settings.integrations.integration_sync_schedule_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="jiraScheduleForm.errors.sync_schedule"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="jiraScheduleForm.processing"
                            data-test="save-jira-sync-schedule-button"
                        >
                            <Save class="h-4 w-4" />
                            {{ t('settings.integrations.save_sync_schedule') }}
                        </Button>
                    </form>
                    <p
                        v-else
                        class="border-t pt-4 text-sm text-muted-foreground"
                    >
                        {{ t('settings.integrations.sync_schedule') }}:
                        {{
                            t(
                                `settings.integrations.sync_schedules.${jiraIntegration.sync_schedule}`,
                            )
                        }}
                    </p>
                </div>

                <div v-if="canManage" class="space-y-4 rounded-lg border p-4">
                    <div class="space-y-1">
                        <h2 class="font-medium">
                            {{
                                jiraIntegration
                                    ? t(
                                          'settings.integrations.update_jira_title',
                                      )
                                    : t(
                                          'settings.integrations.connect_jira_title',
                                      )
                            }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                t(
                                    'settings.integrations.connect_jira_description',
                                )
                            }}
                        </p>
                    </div>

                    <form class="space-y-4" @submit.prevent="connectJira">
                        <div class="grid gap-2">
                            <Label for="jira_label">{{
                                t('settings.integrations.label')
                            }}</Label>
                            <Input
                                id="jira_label"
                                v-model="jiraForm.label"
                                :placeholder="t('settings.integrations.jira')"
                            />
                            <InputError :message="jiraForm.errors.label" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="jira_base_url">{{
                                t('settings.integrations.jira_base_url')
                            }}</Label>
                            <Input
                                id="jira_base_url"
                                v-model="jiraForm.base_url"
                                :placeholder="
                                    t(
                                        'settings.integrations.jira_base_url_placeholder',
                                    )
                                "
                                data-test="jira-base-url-input"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.jira_base_url_help',
                                    )
                                }}
                            </p>
                            <InputError :message="jiraForm.errors.base_url" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="jira_email">{{
                                t('settings.integrations.jira_email')
                            }}</Label>
                            <Input
                                id="jira_email"
                                v-model="jiraForm.email"
                                type="email"
                                :placeholder="
                                    t(
                                        'settings.integrations.jira_email_placeholder',
                                    )
                                "
                                data-test="jira-email-input"
                            />
                            <InputError :message="jiraForm.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="jira_api_token">{{
                                t('settings.integrations.jira_api_token')
                            }}</Label>
                            <PasswordInput
                                id="jira_api_token"
                                v-model="jiraForm.api_token"
                                :placeholder="
                                    t(
                                        'settings.integrations.jira_api_token_placeholder',
                                    )
                                "
                                data-test="jira-api-token-input"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.jira_api_token_help',
                                    )
                                }}
                            </p>
                            <InputError :message="jiraForm.errors.api_token" />
                        </div>

                        <Button
                            type="submit"
                            :disabled="jiraForm.processing"
                            data-test="connect-jira-button"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                jiraIntegration
                                    ? t('settings.integrations.update_token')
                                    : t('settings.integrations.connect_jira')
                            }}
                        </Button>
                    </form>
                </div>

                <p
                    v-else-if="!jiraIntegration"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('settings.integrations.provider_not_connected') }}
                </p>
            </TabsContent>

            <TabsContent value="azure-devops" class="space-y-6">
                <div
                    v-if="azureDevOpsIntegration"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 font-medium">
                                <GitBranch class="h-4 w-4" />
                                {{
                                    azureDevOpsIntegration.label ||
                                    t('settings.integrations.azure_devops')
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('settings.integrations.status') }}:
                                {{
                                    t(
                                        `settings.integrations.statuses.${azureDevOpsIntegration.status}`,
                                    )
                                }}
                            </p>
                            <p
                                v-if="azureDevOpsIntegration.organization"
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    t(
                                        'settings.integrations.azure_devops_organization',
                                    )
                                }}:
                                {{ azureDevOpsIntegration.organization }}
                            </p>
                            <p
                                v-if="azureDevOpsIntegration.base_url"
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    t(
                                        'settings.integrations.azure_devops_base_url',
                                    )
                                }}:
                                {{ azureDevOpsIntegration.base_url }}
                            </p>
                            <p
                                v-if="azureDevOpsIntegration.last_verified_at"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.last_verified') }}:
                                {{
                                    new Date(
                                        azureDevOpsIntegration.last_verified_at,
                                    ).toLocaleString()
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="outline"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            data-test="disconnect-azure-devops-button"
                            @click="
                                disconnectTarget = {
                                    type: 'integration',
                                    id: azureDevOpsIntegration.id,
                                }
                            "
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ t('settings.integrations.disconnect') }}
                        </Button>
                    </div>

                    <form
                        v-if="canManage"
                        class="space-y-3 border-t pt-4"
                        @submit.prevent="saveAzureDevOpsSyncSchedule"
                    >
                        <div class="grid gap-2">
                            <Label for="azure_devops_sync_schedule">{{
                                t('settings.integrations.sync_schedule')
                            }}</Label>
                            <Select
                                :model-value="
                                    azureDevOpsScheduleForm.sync_schedule
                                "
                                @update:model-value="
                                    (value) => {
                                        if (typeof value === 'string') {
                                            azureDevOpsScheduleForm.sync_schedule =
                                                value;
                                        }
                                    }
                                "
                            >
                                <SelectTrigger
                                    id="azure_devops_sync_schedule"
                                    class="w-full max-w-xs"
                                    data-test="azure-devops-sync-schedule-select"
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
                                {{
                                    t(
                                        'settings.integrations.integration_sync_schedule_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="
                                    azureDevOpsScheduleForm.errors.sync_schedule
                                "
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="azureDevOpsScheduleForm.processing"
                            data-test="save-azure-devops-sync-schedule-button"
                        >
                            <Save class="h-4 w-4" />
                            {{ t('settings.integrations.save_sync_schedule') }}
                        </Button>
                    </form>
                    <p
                        v-else
                        class="border-t pt-4 text-sm text-muted-foreground"
                    >
                        {{ t('settings.integrations.sync_schedule') }}:
                        {{
                            t(
                                `settings.integrations.sync_schedules.${azureDevOpsIntegration.sync_schedule}`,
                            )
                        }}
                    </p>
                </div>

                <div v-if="canManage" class="space-y-4 rounded-lg border p-4">
                    <div class="space-y-1">
                        <h2 class="font-medium">
                            {{
                                azureDevOpsIntegration
                                    ? t(
                                          'settings.integrations.update_azure_devops_title',
                                      )
                                    : t(
                                          'settings.integrations.connect_azure_devops_title',
                                      )
                            }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                t(
                                    'settings.integrations.connect_azure_devops_description',
                                )
                            }}
                        </p>
                    </div>

                    <form
                        class="space-y-4"
                        @submit.prevent="connectAzureDevOps"
                    >
                        <div class="grid gap-2">
                            <Label for="azure_devops_label">{{
                                t('settings.integrations.label')
                            }}</Label>
                            <Input
                                id="azure_devops_label"
                                v-model="azureDevOpsForm.label"
                                :placeholder="
                                    t('settings.integrations.azure_devops')
                                "
                            />
                            <InputError
                                :message="azureDevOpsForm.errors.label"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="azure_devops_organization">{{
                                t(
                                    'settings.integrations.azure_devops_organization',
                                )
                            }}</Label>
                            <Input
                                id="azure_devops_organization"
                                v-model="azureDevOpsForm.organization"
                                :placeholder="
                                    t(
                                        'settings.integrations.azure_devops_organization_placeholder',
                                    )
                                "
                                required
                                data-test="azure-devops-organization-input"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.azure_devops_organization_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="azureDevOpsForm.errors.organization"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="azure_devops_pat">{{
                                t('settings.integrations.azure_devops_pat')
                            }}</Label>
                            <PasswordInput
                                id="azure_devops_pat"
                                v-model="azureDevOpsForm.pat"
                                :placeholder="
                                    t('settings.integrations.azure_devops_pat')
                                "
                                data-test="azure-devops-pat-input"
                            />
                            <InputError :message="azureDevOpsForm.errors.pat" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="azure_devops_base_url">{{
                                t('settings.integrations.azure_devops_base_url')
                            }}</Label>
                            <Input
                                id="azure_devops_base_url"
                                v-model="azureDevOpsForm.base_url"
                                :placeholder="
                                    t(
                                        'settings.integrations.azure_devops_base_url_placeholder',
                                    )
                                "
                                data-test="azure-devops-base-url-input"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.azure_devops_base_url_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="azureDevOpsForm.errors.base_url"
                            />
                        </div>

                        <Button
                            type="submit"
                            :disabled="azureDevOpsForm.processing"
                            data-test="connect-azure-devops-button"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                azureDevOpsIntegration
                                    ? t('settings.integrations.update_token')
                                    : t(
                                          'settings.integrations.connect_azure_devops_title',
                                      )
                            }}
                        </Button>
                    </form>
                </div>

                <p
                    v-else-if="!azureDevOpsIntegration"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('settings.integrations.provider_not_connected') }}
                </p>
            </TabsContent>

            <TabsContent value="snyk" class="space-y-6">
                <div
                    v-if="snykIntegration"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 font-medium">
                                <Shield class="h-4 w-4" />
                                {{
                                    snykIntegration.label ||
                                    t('settings.integrations.snyk')
                                }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('settings.integrations.status') }}:
                                {{
                                    t(
                                        `settings.integrations.statuses.${snykIntegration.status}`,
                                    )
                                }}
                            </p>
                            <p
                                v-if="snykIntegration.base_url"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.snyk_base_url') }}:
                                {{ snykIntegration.base_url }}
                            </p>
                            <p
                                v-if="snykIntegration.last_verified_at"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.last_verified') }}:
                                {{
                                    new Date(
                                        snykIntegration.last_verified_at,
                                    ).toLocaleString()
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="outline"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            data-test="disconnect-snyk-button"
                            @click="
                                disconnectTarget = {
                                    type: 'integration',
                                    id: snykIntegration.id,
                                }
                            "
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ t('settings.integrations.disconnect') }}
                        </Button>
                    </div>

                    <form
                        v-if="canManage"
                        class="space-y-3 border-t pt-4"
                        @submit.prevent="saveSnykSyncSchedule"
                    >
                        <div class="grid gap-2">
                            <Label for="snyk_sync_schedule">{{
                                t('settings.integrations.sync_schedule')
                            }}</Label>
                            <Select
                                :model-value="snykScheduleForm.sync_schedule"
                                @update:model-value="
                                    (value) => {
                                        if (typeof value === 'string') {
                                            snykScheduleForm.sync_schedule =
                                                value;
                                        }
                                    }
                                "
                            >
                                <SelectTrigger
                                    id="snyk_sync_schedule"
                                    class="w-full max-w-xs"
                                    data-test="snyk-sync-schedule-select"
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
                                {{
                                    t(
                                        'settings.integrations.integration_sync_schedule_help',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="snykScheduleForm.errors.sync_schedule"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="snykScheduleForm.processing"
                            data-test="save-snyk-sync-schedule-button"
                        >
                            <Save class="h-4 w-4" />
                            {{ t('settings.integrations.save_sync_schedule') }}
                        </Button>
                    </form>
                    <p
                        v-else
                        class="border-t pt-4 text-sm text-muted-foreground"
                    >
                        {{ t('settings.integrations.sync_schedule') }}:
                        {{
                            t(
                                `settings.integrations.sync_schedules.${snykIntegration.sync_schedule}`,
                            )
                        }}
                    </p>
                </div>

                <div v-if="canManage" class="space-y-4 rounded-lg border p-4">
                    <div>
                        <h3 class="font-medium">
                            {{
                                snykIntegration
                                    ? t(
                                          'settings.integrations.update_snyk_title',
                                      )
                                    : t(
                                          'settings.integrations.connect_snyk_title',
                                      )
                            }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{
                                t(
                                    'settings.integrations.connect_snyk_description',
                                )
                            }}
                        </p>
                    </div>
                    <form class="space-y-4" @submit.prevent="connectSnyk">
                        <div class="grid gap-2">
                            <Label for="snyk_label">{{
                                t('settings.integrations.label')
                            }}</Label>
                            <Input
                                id="snyk_label"
                                v-model="snykForm.label"
                                :placeholder="t('settings.integrations.snyk')"
                            />
                            <InputError :message="snykForm.errors.label" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="snyk_base_url">{{
                                t('settings.integrations.snyk_base_url')
                            }}</Label>
                            <Input
                                id="snyk_base_url"
                                v-model="snykForm.base_url"
                                :placeholder="
                                    t(
                                        'settings.integrations.snyk_base_url_placeholder',
                                    )
                                "
                                data-test="snyk-base-url-input"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.snyk_base_url_help',
                                    )
                                }}
                            </p>
                            <InputError :message="snykForm.errors.base_url" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="snyk_api_token">{{
                                t('settings.integrations.snyk_api_token')
                            }}</Label>
                            <PasswordInput
                                id="snyk_api_token"
                                v-model="snykForm.api_token"
                                :placeholder="
                                    t(
                                        'settings.integrations.snyk_api_token_placeholder',
                                    )
                                "
                                data-test="snyk-api-token-input"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'settings.integrations.snyk_api_token_help',
                                    )
                                }}
                            </p>
                            <InputError :message="snykForm.errors.api_token" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="snykForm.processing"
                            data-test="connect-snyk-button"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                snykIntegration
                                    ? t('settings.integrations.update_token')
                                    : t('settings.integrations.connect_snyk')
                            }}
                        </Button>
                    </form>
                </div>

                <p
                    v-else-if="!snykIntegration"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('settings.integrations.provider_not_connected') }}
                </p>
            </TabsContent>

            <TabsContent value="sarif" class="space-y-6">
                <div
                    v-if="sarifIntegration"
                    class="space-y-4 rounded-lg border p-4"
                    data-test="sarif-integration-card"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <p class="font-medium">
                                {{
                                    sarifIntegration.label ||
                                    t('settings.integrations.sarif')
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    t('settings.integrations.auth_methods.none')
                                }}
                            </p>
                            <p
                                v-if="sarifIntegration.last_verified_at"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('settings.integrations.last_verified') }}:
                                {{
                                    new Date(
                                        sarifIntegration.last_verified_at,
                                    ).toLocaleString()
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="outline"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="
                                disconnectTarget = {
                                    type: 'integration',
                                    id: sarifIntegration.id,
                                }
                            "
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ t('settings.integrations.disconnect') }}
                        </Button>
                    </div>
                </div>

                <div v-if="canManage" class="space-y-4 rounded-lg border p-4">
                    <div>
                        <h3 class="font-medium">
                            {{
                                sarifIntegration
                                    ? t(
                                          'settings.integrations.update_sarif_title',
                                      )
                                    : t(
                                          'settings.integrations.connect_sarif_title',
                                      )
                            }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{
                                t(
                                    'settings.integrations.connect_sarif_description',
                                )
                            }}
                        </p>
                    </div>
                    <form
                        class="space-y-3"
                        data-test="sarif-enable-form"
                        @submit.prevent="connectSarif"
                    >
                        <div class="grid gap-2">
                            <Label for="sarif_label">{{
                                t('settings.integrations.label')
                            }}</Label>
                            <Input
                                id="sarif_label"
                                v-model="sarifForm.label"
                                :placeholder="t('settings.integrations.sarif')"
                            />
                            <InputError :message="sarifForm.errors.label" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="sarifForm.processing"
                            data-test="enable-sarif-button"
                        >
                            <Save class="h-4 w-4" />
                            {{
                                sarifIntegration
                                    ? t('common.save')
                                    : t('settings.integrations.enable_sarif')
                            }}
                        </Button>
                    </form>
                </div>

                <p
                    v-else-if="!sarifIntegration"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('settings.integrations.provider_not_connected') }}
                </p>
            </TabsContent>
        </Tabs>

        <p v-else class="text-sm text-muted-foreground">
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
