import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';
import { panelConfig } from '@/config/panels';

export { default as Panel } from './Panel.vue';
export { default as PanelDescription } from './PanelDescription.vue';
export { default as PanelTitle } from './PanelTitle.vue';

const variantClasses = Object.fromEntries(
    Object.entries(panelConfig.variants).map(([key, tone]) => [key, tone.panel]),
) as Record<keyof typeof panelConfig.variants, string>;

export const panelVariants = cva('rounded-lg border text-sm', {
    variants: {
        variant: variantClasses,
        size: {
            sm: 'p-3',
            default: 'p-4',
            lg: 'p-5',
        },
    },
    defaultVariants: {
        variant: panelConfig.defaultVariant,
        size: 'default',
    },
});

export type PanelVariants = VariantProps<typeof panelVariants>;
