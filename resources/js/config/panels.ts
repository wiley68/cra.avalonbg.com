/**
 * Panel style configuration.
 *
 * Keep variant keys in sync with config/panels.php.
 * Visual tokens live in resources/css/app.css (--panel-*).
 */

export const panelVariantsList = [
    'standard',
    'info',
    'important',
    'success',
    'error',
] as const;

export type PanelVariant = (typeof panelVariantsList)[number];

export type PanelToneClasses = {
    panel: string;
};

export const panelConfig = {
    defaultVariant: 'standard' as PanelVariant,
    variants: {
        standard: {
            panel: 'border-border bg-card text-card-foreground',
        },
        info: {
            panel: 'border-panel-info-border bg-panel-info text-panel-info-foreground',
        },
        important: {
            panel: 'border-panel-important-border bg-panel-important text-panel-important-foreground',
        },
        success: {
            panel: 'border-panel-success-border bg-panel-success text-panel-success-foreground',
        },
        error: {
            panel: 'border-panel-error-border bg-panel-error text-panel-error-foreground',
        },
    } satisfies Record<PanelVariant, PanelToneClasses>,
} as const;

export function panelTone(
    variant: PanelVariant = panelConfig.defaultVariant,
): PanelToneClasses {
    return panelConfig.variants[variant];
}
