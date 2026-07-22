import { createInertiaApp, router } from '@inertiajs/vue3';
import {
    initializeTheme,
    syncThemeFromPageProps,
} from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'auditor/GuestShow':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp(_app, { page }) {
        syncThemeFromPageProps(page.props);
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('success', (event) => {
    syncThemeFromPageProps(event.detail.page.props);
});

initializeTheme();
initializeFlashToast();
