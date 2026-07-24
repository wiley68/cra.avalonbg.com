<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { reactive } from 'vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { aiTriage as importAiTriage } from '@/routes/products/import-suggestions';
import { aiTriage as vcsAiTriage } from '@/routes/products/vcs-suggestions';

const props = defineProps<{
    productId: number;
    suggestionId: number;
    source: 'import' | 'vcs';
}>();

const { t } = useTranslations();

const draft = reactive({
    loading: false,
    error: '' as string,
    summary_markdown: '' as string,
    suggested_severity: '' as string,
    disclaimer: '' as string,
});

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const requestTriage = async (): Promise<void> => {
    draft.loading = true;
    draft.error = '';
    draft.summary_markdown = '';
    draft.suggested_severity = '';
    draft.disclaimer = '';

    const route =
        props.source === 'vcs'
            ? vcsAiTriage({
                  product: props.productId,
                  suggestion: props.suggestionId,
              })
            : importAiTriage({
                  product: props.productId,
                  suggestion: props.suggestionId,
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
            suggested_severity?: string;
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
                t('products.integrations.suggestions.ai_triage_error');

            return;
        }

        draft.summary_markdown = data.summary_markdown ?? '';
        draft.suggested_severity = data.suggested_severity ?? '';
        draft.disclaimer =
            data.disclaimer ||
            t('products.integrations.suggestions.ai_triage_disclaimer');

        if (!draft.summary_markdown) {
            draft.error = t(
                'products.integrations.suggestions.ai_triage_error',
            );
        }
    } catch {
        draft.error = t('products.integrations.suggestions.ai_triage_error');
    } finally {
        draft.loading = false;
    }
};

const discardTriage = (): void => {
    draft.summary_markdown = '';
    draft.suggested_severity = '';
    draft.disclaimer = '';
    draft.error = '';
};
</script>

<template>
    <div class="w-full space-y-2" data-test="imported-finding-ai-triage">
        <Button
            type="button"
            size="sm"
            variant="outline"
            :disabled="draft.loading"
            data-test="imported-finding-ai-triage-suggest"
            @click="requestTriage"
        >
            <Sparkles class="h-4 w-4" />
            {{
                draft.loading
                    ? t('products.integrations.suggestions.ai_triage_loading')
                    : t('products.integrations.suggestions.ai_triage_suggest')
            }}
        </Button>

        <div
            v-if="draft.error"
            class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
        >
            {{ draft.error }}
        </div>

        <div
            v-if="draft.summary_markdown"
            class="space-y-3 rounded-md border border-border bg-muted/30 p-3"
            data-test="imported-finding-ai-triage-draft"
        >
            <p class="text-sm text-muted-foreground">
                {{ draft.disclaimer }}
            </p>
            <p
                v-if="draft.suggested_severity"
                class="text-xs text-muted-foreground uppercase"
            >
                {{
                    t(
                        'products.integrations.suggestions.ai_triage_suggested_severity',
                    )
                }}:
                {{ draft.suggested_severity }}
            </p>
            <MarkdownPreview
                :source="draft.summary_markdown"
                :empty-label="
                    t('products.integrations.suggestions.ai_triage_empty')
                "
            />
            <Button
                type="button"
                size="sm"
                variant="outline"
                data-test="imported-finding-ai-triage-discard"
                @click="discardTriage"
            >
                {{ t('products.integrations.suggestions.ai_triage_discard') }}
            </Button>
        </div>
    </div>
</template>
