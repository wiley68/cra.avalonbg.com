import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();
    const permissions = ((page.props as Record<string, any>).auth?.user?.permissions ?? []) as string[];

    const can = (permission: string): boolean => permissions.includes(permission);

    return { can, permissions };
}

