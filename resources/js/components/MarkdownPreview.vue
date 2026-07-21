<script setup lang="ts">
import { computed } from 'vue';
import { renderMarkdown } from '@/lib/markdown';

const props = defineProps<{
    source: string;
    emptyLabel?: string;
}>();

const html = computed(() => renderMarkdown(props.source));
const isEmpty = computed(() => props.source.trim() === '');
</script>

<template>
    <div
        v-if="isEmpty"
        class="rounded-md border border-dashed px-3 py-6 text-sm text-muted-foreground"
    >
        {{ emptyLabel }}
    </div>
    <div
        v-else
        class="policy-markdown max-h-128 overflow-y-auto rounded-md border bg-background px-4 py-3 text-sm leading-relaxed"
        v-html="html"
    />
</template>

<style scoped>
.policy-markdown :deep(h1),
.policy-markdown :deep(h2),
.policy-markdown :deep(h3) {
    font-weight: 600;
    margin: 0.75rem 0 0.35rem;
    line-height: 1.3;
}

.policy-markdown :deep(h1) {
    font-size: 1.25rem;
}

.policy-markdown :deep(h2) {
    font-size: 1.1rem;
}

.policy-markdown :deep(h3) {
    font-size: 1rem;
}

.policy-markdown :deep(p),
.policy-markdown :deep(ul),
.policy-markdown :deep(ol) {
    margin: 0.4rem 0;
}

.policy-markdown :deep(ul),
.policy-markdown :deep(ol) {
    padding-left: 1.25rem;
}

.policy-markdown :deep(li) {
    margin: 0.15rem 0;
}

.policy-markdown :deep(code) {
    border-radius: 0.25rem;
    background: color-mix(in oklab, var(--muted) 80%, transparent);
    padding: 0.1rem 0.3rem;
    font-size: 0.85em;
}

.policy-markdown :deep(pre) {
    overflow-x: auto;
    border-radius: 0.375rem;
    background: color-mix(in oklab, var(--muted) 80%, transparent);
    padding: 0.75rem;
    margin: 0.5rem 0;
}

.policy-markdown :deep(pre code) {
    background: transparent;
    padding: 0;
}

.policy-markdown :deep(blockquote) {
    border-left: 3px solid var(--border);
    color: var(--muted-foreground);
    margin: 0.5rem 0;
    padding-left: 0.75rem;
}

.policy-markdown :deep(a) {
    color: var(--primary);
    text-decoration: underline;
}
</style>
