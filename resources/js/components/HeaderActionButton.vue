<script setup lang="ts">
import { Link, type InertiaLinkProps } from '@inertiajs/vue3';
import {
    computed,
    inject,
    onMounted,
    onUnmounted,
    type ComputedRef,
} from 'vue';
import type { PageFormHeaderRegistration } from '@/components/PageFormHeaderActions.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { toUrl } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        /** Visible label when not icon-only; also used as tooltip / aria-label. */
        label: string;
        /** Back stays labeled even in compact mode. */
        isBack?: boolean;
        href?: NonNullable<InertiaLinkProps['href']>;
        /** Use Inertia Link instead of <a> when href is set. */
        inertia?: boolean;
        target?: string;
        rel?: string;
        type?: 'button' | 'submit';
        variant?:
            | 'default'
            | 'destructive'
            | 'outline'
            | 'secondary'
            | 'ghost'
            | 'link';
        disabled?: boolean;
    }>(),
    {
        isBack: false,
        inertia: true,
        type: 'button',
        variant: 'outline',
        disabled: false,
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const registration = inject<PageFormHeaderRegistration | null>(
    'pageFormHeaderRegistration',
    null,
);

const compactFallback = computed(() => false);
const compact = inject<ComputedRef<boolean>>(
    'pageFormHeaderCompact',
    compactFallback,
);

onMounted(() => {
    registration?.register();
});

onUnmounted(() => {
    registration?.unregister();
});

const iconOnly = computed(() => compact.value && !props.isBack);
const showLabel = computed(() => !iconOnly.value);

const buttonSize = computed(() => (iconOnly.value ? 'icon' : 'default'));
</script>

<template>
    <TooltipProvider v-if="iconOnly" :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    v-if="href && inertia"
                    as-child
                    :variant="variant"
                    :size="buttonSize"
                    :disabled="disabled"
                    :aria-label="label"
                >
                    <Link :href="href">
                        <slot />
                    </Link>
                </Button>
                <Button
                    v-else-if="href"
                    as-child
                    :variant="variant"
                    :size="buttonSize"
                    :disabled="disabled"
                    :aria-label="label"
                >
                    <a :href="toUrl(href)" :target="target" :rel="rel">
                        <slot />
                    </a>
                </Button>
                <Button
                    v-else
                    :type="type"
                    :variant="variant"
                    :size="buttonSize"
                    :disabled="disabled"
                    :aria-label="label"
                    @click="emit('click', $event)"
                >
                    <slot />
                </Button>
            </TooltipTrigger>
            <TooltipContent side="bottom">
                {{ label }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>

    <template v-else>
        <Button
            v-if="href && inertia"
            as-child
            :variant="variant"
            :size="buttonSize"
            :disabled="disabled"
            :aria-label="label"
        >
            <Link :href="href">
                <slot />
                <span v-if="showLabel">{{ label }}</span>
            </Link>
        </Button>
        <Button
            v-else-if="href"
            as-child
            :variant="variant"
            :size="buttonSize"
            :disabled="disabled"
            :aria-label="label"
        >
            <a :href="toUrl(href)" :target="target" :rel="rel">
                <slot />
                <span v-if="showLabel">{{ label }}</span>
            </a>
        </Button>
        <Button
            v-else
            :type="type"
            :variant="variant"
            :size="buttonSize"
            :disabled="disabled"
            :aria-label="label"
            @click="emit('click', $event)"
        >
            <slot />
            <span v-if="showLabel">{{ label }}</span>
        </Button>
    </template>
</template>
