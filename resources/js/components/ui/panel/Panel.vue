<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { PanelVariants } from '.';
import { cn } from '@/lib/utils';
import { panelVariants } from '.';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        variant?: PanelVariants['variant'];
        size?: PanelVariants['size'];
    }>(),
    {
        variant: 'standard',
        size: 'default',
    },
);
</script>

<template>
    <div
        data-slot="panel"
        :data-variant="variant"
        :class="cn(panelVariants({ variant, size }), props.class)"
    >
        <slot />
    </div>
</template>
