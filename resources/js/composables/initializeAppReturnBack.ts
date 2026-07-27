import {
    initializeReturnBackTracking,
    registerReturnBackRule,
} from '@/composables/useReturnBack';

/**
 * Import specialized rules so they register before tracking starts.
 * Add new return-back rules here (or register them from their composable module).
 */
import '@/composables/useProductEditBack';
import '@/composables/useTaskEditBack';
import '@/composables/useRiskEditBack';
import '@/composables/useVulnerabilityEditBack';
import '@/composables/useSdlEditBack';
import '@/composables/useProductModuleBack';

export function initializeAppReturnBackTracking(): void {
    initializeReturnBackTracking();
}

export { registerReturnBackRule, initializeReturnBackTracking };
