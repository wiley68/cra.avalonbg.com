<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Save, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Switch } from '@/components/ui/switch';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    destroy as destroySso,
    edit as editSso,
    update as updateSso,
} from '@/routes/settings/sso';

type SsoConnection = {
    id: number;
    provider: string;
    issuer: string;
    client_id: string;
    has_client_secret: boolean;
    allowed_email_domains: string[];
    is_enabled: boolean;
} | null;

type ProviderOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    organization: {
        id: number;
        name: string;
        slug: string;
        subscription_plan: string;
        sso_enabled: boolean;
        can_use_sso: boolean;
        can_toggle_sso_flag: boolean;
    };
    connection: SsoConnection;
    providers: ProviderOption[];
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [{ titleKey: 'settings.nav.sso', href: editSso() }]);

const form = useForm({
    provider: props.connection?.provider ?? 'generic',
    issuer: props.connection?.issuer ?? '',
    client_id: props.connection?.client_id ?? '',
    client_secret: '',
    allowed_email_domains: (props.connection?.allowed_email_domains ?? []).join(
        ', ',
    ),
    is_enabled: Boolean(props.connection?.is_enabled),
    sso_enabled: Boolean(props.organization.sso_enabled),
});

const planLabel = computed(() =>
    t(`billing.plans.${props.organization.subscription_plan}`),
);

/** Backend bag key (not a form field) — e.g. plan_not_allowed. */
const ssoBagError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.sso;
});

const submit = () => {
    form.put(updateSso().url, { preserveScroll: true });
};

const disconnect = () => {
    router.delete(destroySso().url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('settings.nav.sso')" />

    <div class="space-y-6">
        <Heading :title="t('sso.title')" :description="t('sso.description')" />

        <p class="text-sm text-muted-foreground">
            {{ t('sso.current_plan') }}: {{ planLabel }}
        </p>

        <div
            v-if="
                !organization.can_use_sso && !organization.can_toggle_sso_flag
            "
            class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
        >
            {{ t('sso.plan_locked') }}
        </div>

        <form v-else class="space-y-6" @submit.prevent="submit">
            <div
                v-if="organization.can_toggle_sso_flag"
                class="flex items-center gap-3"
            >
                <Switch
                    id="sso_enabled"
                    v-model="form.sso_enabled"
                    class="cursor-pointer"
                />
                <Label for="sso_enabled" class="cursor-pointer">
                    {{ t('sso.enable_for_standard') }}
                </Label>
            </div>
            <InputError :message="form.errors.sso_enabled" />
            <InputError :message="ssoBagError" />

            <template v-if="organization.can_use_sso || form.sso_enabled">
                <div class="grid gap-2">
                    <Label for="provider">{{ t('sso.provider') }}</Label>
                    <Select
                        :model-value="form.provider || undefined"
                        @update:model-value="
                            (value) => {
                                if (typeof value === 'string') {
                                    form.provider = value;
                                }
                            }
                        "
                    >
                        <SelectTrigger id="provider" class="w-full">
                            <SelectValue :placeholder="t('sso.provider')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="provider in providers"
                                :key="provider.value"
                                :value="provider.value"
                            >
                                {{ provider.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.provider" />
                </div>

                <div class="grid gap-2">
                    <Label for="issuer">{{ t('sso.issuer') }}</Label>
                    <Input
                        id="issuer"
                        v-model="form.issuer"
                        type="url"
                        required
                        :placeholder="t('sso.issuer_placeholder')"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('sso.issuer_help') }}
                    </p>
                    <InputError :message="form.errors.issuer" />
                </div>

                <div class="grid gap-2">
                    <Label for="client_id">{{ t('sso.client_id') }}</Label>
                    <Input
                        id="client_id"
                        v-model="form.client_id"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.client_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="client_secret">{{
                        t('sso.client_secret')
                    }}</Label>
                    <Input
                        id="client_secret"
                        v-model="form.client_secret"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            connection?.has_client_secret
                                ? t('sso.client_secret_keep')
                                : undefined
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('sso.client_secret_help') }}
                    </p>
                    <InputError :message="form.errors.client_secret" />
                </div>

                <div class="grid gap-2">
                    <Label for="allowed_email_domains">{{
                        t('sso.allowed_domains')
                    }}</Label>
                    <Input
                        id="allowed_email_domains"
                        v-model="form.allowed_email_domains"
                        required
                        :placeholder="t('sso.allowed_domains_placeholder')"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('sso.allowed_domains_help') }}
                    </p>
                    <InputError :message="form.errors.allowed_email_domains" />
                </div>

                <div class="flex items-center gap-3">
                    <Switch
                        id="is_enabled"
                        v-model="form.is_enabled"
                        class="cursor-pointer"
                    />
                    <Label for="is_enabled" class="cursor-pointer">
                        {{ t('sso.is_enabled') }}
                    </Label>
                </div>
                <InputError :message="form.errors.is_enabled" />

                <div class="flex items-center justify-between gap-3">
                    <Button type="submit" :disabled="form.processing">
                        <Save class="h-4 w-4" />
                        {{ t('common.save') }}
                    </Button>

                    <Button
                        v-if="connection"
                        type="button"
                        variant="outline"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        :disabled="form.processing"
                        @click="disconnect"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('sso.disconnect') }}
                    </Button>
                </div>
            </template>
        </form>
    </div>
</template>
