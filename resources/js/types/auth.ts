export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    is_platform_admin?: boolean;
    must_change_password?: boolean;
    permissions?: string[];
    role?: string | null;
    role_label?: string | null;
    can_manage_users?: boolean;
    can_view_products?: boolean;
    can_manage_products?: boolean;
    can_view_requirements?: boolean;
    can_manage_requirements?: boolean;
    can_view_controls?: boolean;
    can_manage_controls?: boolean;
    can_view_risks?: boolean;
    can_manage_risks?: boolean;
    can_view_components?: boolean;
    can_manage_components?: boolean;
    can_manage_organizations?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
