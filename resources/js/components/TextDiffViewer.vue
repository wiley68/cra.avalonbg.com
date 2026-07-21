<script setup lang="ts">
import { computed } from 'vue';
import { buildLineDiff } from '@/lib/textDiff';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    previous: string;
    current: string;
    previousLabel?: string;
    currentLabel?: string;
}>();

const { t } = useTranslations();

const lines = computed(() => buildLineDiff(props.previous, props.current));

const hasChanges = computed(() =>
    lines.value.some((line) => line.type !== 'unchanged'),
);
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
            <span v-if="previousLabel"> − {{ previousLabel }} </span>
            <span v-if="currentLabel"> + {{ currentLabel }} </span>
        </div>

        <p
            v-if="!hasChanges"
            class="rounded-md border border-dashed px-3 py-6 text-sm text-muted-foreground"
        >
            {{ t('policies.diff_unchanged') }}
        </p>

        <div
            v-else
            class="max-h-128 overflow-auto rounded-md border font-mono text-xs leading-5"
        >
            <div
                v-for="(line, index) in lines"
                :key="`${index}-${line.type}`"
                class="px-3 py-0.5 whitespace-pre-wrap"
                :class="{
                    'bg-emerald-500/15 text-emerald-900 dark:text-emerald-200':
                        line.type === 'added',
                    'bg-rose-500/15 text-rose-900 dark:text-rose-200':
                        line.type === 'removed',
                    'text-muted-foreground': line.type === 'unchanged',
                }"
            >
                <span class="mr-2 inline-block w-3 opacity-70 select-none">
                    {{
                        line.type === 'added'
                            ? '+'
                            : line.type === 'removed'
                              ? '−'
                              : ' '
                    }}
                </span>
                {{ line.text || ' ' }}
            </div>
        </div>
    </div>
</template>
