<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';

withDefaults(
    defineProps<{
        htmlFor?: string;
        help: string;
        required?: boolean;
    }>(),
    {
        htmlFor: undefined,
        required: false,
    },
);

const { t } = useTranslations();
</script>

<template>
    <div class="flex items-center gap-1.5">
        <Label :for="htmlFor" class="inline-flex items-center gap-1">
            <slot />
            <span
                v-if="required"
                class="text-destructive"
                :title="t('common.required')"
                aria-hidden="true"
                >*</span
            >
        </Label>
        <TooltipProvider :delay-duration="200">
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        tabindex="-1"
                        class="inline-flex shrink-0 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="t('common.field_help')"
                    >
                        <CircleHelp class="h-3.5 w-3.5" />
                    </button>
                </TooltipTrigger>
                <TooltipContent
                    side="top"
                    class="max-w-xs text-left leading-relaxed"
                >
                    {{ help }}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>
