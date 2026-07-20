<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';

defineProps<{
    risk: {
        asset: string | null;
        threat: string | null;
        weakness: string | null;
        attack_scenario: string | null;
    };
}>();

const { t } = useTranslations();

const display = (value: string | null): string => value?.trim() || '—';
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="inline-flex shrink-0 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="t('common.field_help')"
                    @click.stop.prevent
                >
                    <CircleHelp class="h-3.5 w-3.5" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                side="top"
                class="max-w-sm space-y-2 text-left leading-relaxed"
            >
                <div>
                    <p
                        class="text-[10px] font-medium tracking-wide uppercase opacity-80"
                    >
                        {{ t('products.risks.fields.asset') }}
                    </p>
                    <p class="whitespace-pre-wrap">{{ display(risk.asset) }}</p>
                </div>
                <div>
                    <p
                        class="text-[10px] font-medium tracking-wide uppercase opacity-80"
                    >
                        {{ t('products.risks.fields.threat') }}
                    </p>
                    <p class="whitespace-pre-wrap">
                        {{ display(risk.threat) }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-[10px] font-medium tracking-wide uppercase opacity-80"
                    >
                        {{ t('products.risks.fields.weakness') }}
                    </p>
                    <p class="whitespace-pre-wrap">
                        {{ display(risk.weakness) }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-[10px] font-medium tracking-wide uppercase opacity-80"
                    >
                        {{ t('products.risks.fields.attack_scenario') }}
                    </p>
                    <p class="whitespace-pre-wrap">
                        {{ display(risk.attack_scenario) }}
                    </p>
                </div>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
