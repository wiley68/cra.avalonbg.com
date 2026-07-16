<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    hideHeading?: boolean;
    autoStart?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
    hideHeading: false,
    autoStart: false,
});

const { t } = useTranslations();
const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);
const autoStarting = ref(false);

onMounted(() => {
    if (
        !props.autoStart ||
        !props.canManageTwoFactor ||
        props.twoFactorEnabled
    ) {
        return;
    }

    autoStarting.value = true;

    router.post(
        enable.url(),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showSetupModal.value = true;
            },
            onFinish: () => {
                autoStarting.value = false;
            },
        },
    );
});

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <div v-if="canManageTwoFactor" class="space-y-6">
        <Heading
            v-if="!hideHeading"
            variant="small"
            :title="t('two_factor.title')"
            :description="t('two_factor.description')"
        />

        <div
            v-if="!twoFactorEnabled"
            class="flex flex-col items-start justify-start space-y-4"
        >
            <p class="text-sm text-muted-foreground">
                {{ t('two_factor.enable_help') }}
            </p>

            <div v-if="autoStart">
                <div
                    v-if="autoStarting"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Spinner />
                    {{ t('two_factor.preparing') }}
                </div>
                <Button
                    v-else-if="hasSetupData && !showSetupModal"
                    @click="showSetupModal = true"
                >
                    <ShieldCheck />{{ t('two_factor.continue_setup') }}
                </Button>
            </div>
            <div v-else>
                <Button v-if="hasSetupData" @click="showSetupModal = true">
                    <ShieldCheck />{{ t('two_factor.continue_setup') }}
                </Button>
                <Form
                    v-else
                    v-bind="enable.form()"
                    @success="showSetupModal = true"
                    #default="{ processing }"
                >
                    <Button type="submit" :disabled="processing">
                        {{ t('two_factor.enable') }}
                    </Button>
                </Form>
            </div>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                {{ t('two_factor.enabled_help') }}
            </p>

            <div class="relative inline">
                <Form v-bind="disable.form()" #default="{ processing }">
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="processing"
                    >
                        {{ t('two_factor.disable') }}
                    </Button>
                </Form>
            </div>

            <TwoFactorRecoveryCodes />
        </div>

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </div>
</template>
