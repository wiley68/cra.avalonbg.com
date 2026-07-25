<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

export type OptionInfoItem = {
    label: string;
    value: string | null | undefined;
};

withDefaults(
    defineProps<{
        items: OptionInfoItem[];
        side?: 'top' | 'right' | 'bottom' | 'left';
        contentClass?: HTMLAttributes['class'];
    }>(),
    {
        side: 'top',
    },
);

const { t } = useTranslations();

const display = (value: string | null | undefined): string =>
    value?.trim() || '—';
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="inline-flex shrink-0 align-middle text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="t('common.field_help')"
                    @click.stop.prevent
                >
                    <CircleHelp class="h-3.5 w-3.5" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                :side="side"
                :class="
                    cn(
                        'max-w-sm space-y-2 text-left leading-relaxed',
                        contentClass,
                    )
                "
            >
                <div v-for="(item, index) in items" :key="index">
                    <p
                        class="text-[10px] font-medium tracking-wide uppercase opacity-80"
                    >
                        {{ item.label }}
                    </p>
                    <p class="whitespace-pre-wrap">{{ display(item.value) }}</p>
                </div>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
