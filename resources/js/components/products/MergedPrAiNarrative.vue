<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { reactive } from 'vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import { Button } from '@/components/ui/button';
import { useAiPlanGate } from '@/composables/useAiPlanGate';
import { useTranslations } from '@/composables/useTranslations';
import { aiNarrative } from '@/routes/products/versions/merged-prs';

const props = defineProps<{
    productId: number;
    versionId: number;
}>();

const { t } = useTranslations();
const { guardAi } = useAiPlanGate();

const draft = reactive({
    loading: false,
    error: '' as string,
    summary_markdown: '' as string,
    disclaimer: '' as string,
});

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const runNarrativeRequest = async (): Promise<void> => {
    draft.loading = true;
    draft.error = '';
    draft.summary_markdown = '';
    draft.disclaimer = '';

    const route = aiNarrative({
        product: props.productId,
        version: props.versionId,
    });

    try {
        const response = await fetch(route.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({}),
        });

        const data = (await response.json().catch(() => ({}))) as {
            summary_markdown?: string;
            disclaimer?: string;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok) {
            const firstError = data.errors
                ? Object.values(data.errors).flat()[0]
                : undefined;
            draft.error =
                firstError ||
                data.message ||
                t('products.versions.merged_prs.ai_narrative_error');

            return;
        }

        if (!data.summary_markdown) {
            draft.error = t('products.versions.merged_prs.ai_narrative_empty');

            return;
        }

        draft.summary_markdown = data.summary_markdown;
        draft.disclaimer =
            data.disclaimer ||
            t('products.versions.merged_prs.ai_narrative_disclaimer');
    } catch {
        draft.error = t('products.versions.merged_prs.ai_narrative_error');
    } finally {
        draft.loading = false;
    }
};

const requestNarrative = (): void => {
    guardAi(() => {
        void runNarrativeRequest();
    });
};

const discard = (): void => {
    draft.error = '';
    draft.summary_markdown = '';
    draft.disclaimer = '';
};
</script>

<template>
    <div class="space-y-3 rounded-md border border-dashed p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium">
                {{ t('products.versions.merged_prs.ai_narrative_title') }}
            </p>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="draft.loading"
                    @click="requestNarrative"
                >
                    <Sparkles class="h-4 w-4" />
                    {{
                        draft.loading
                            ? t(
                                  'products.versions.merged_prs.ai_narrative_loading',
                              )
                            : t(
                                  'products.versions.merged_prs.ai_narrative_suggest',
                              )
                    }}
                </Button>
                <Button
                    v-if="draft.summary_markdown"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="discard"
                >
                    {{ t('products.versions.merged_prs.ai_narrative_discard') }}
                </Button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            {{ t('products.versions.merged_prs.ai_narrative_help') }}
        </p>

        <p v-if="draft.error" class="text-sm text-destructive">
            {{ draft.error }}
        </p>

        <div v-if="draft.summary_markdown" class="space-y-2">
            <p
                v-if="draft.disclaimer"
                class="text-xs text-amber-800 dark:text-amber-200"
            >
                {{ draft.disclaimer }}
            </p>
            <MarkdownPreview :source="draft.summary_markdown" />
        </div>
    </div>
</template>
