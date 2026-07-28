import { usePage } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';

type SharedOrganization = {
    can_use_ai?: boolean;
} | null;

type SharedAuthUser = {
    can_manage_billing?: boolean;
} | null;

/** Shared across all AI entry points so one dialog can open from any surface. */
const planLockedOpen: Ref<boolean> = ref(false);

export function useAiPlanGate() {
    const page = usePage();

    const canUseAi = computed(() => {
        const organization = page.props.organization as SharedOrganization;

        return Boolean(organization?.can_use_ai);
    });

    const canManageBilling = computed(() => {
        const user = page.props.auth?.user as SharedAuthUser | undefined;

        return Boolean(user?.can_manage_billing);
    });

    const openPlanLocked = (): void => {
        planLockedOpen.value = true;
    };

    /**
     * Run an AI action only on paid plans; otherwise open the upgrade dialog.
     */
    const guardAi = (action: () => void): void => {
        if (!canUseAi.value) {
            openPlanLocked();

            return;
        }

        action();
    };

    return {
        canUseAi,
        canManageBilling,
        planLockedOpen,
        openPlanLocked,
        guardAi,
    };
}
