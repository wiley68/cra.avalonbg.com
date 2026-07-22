<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import TextDiffViewer from '@/components/TextDiffViewer.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/composables/useTranslations';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        previousBody?: string | null;
        previousLabel?: string | null;
        currentLabel?: string | null;
        disabled?: boolean;
        required?: boolean;
        error?: string;
        inputId?: string;
        label?: string;
        help?: string;
        writeLabel?: string;
        previewLabel?: string;
        diffLabel?: string;
        emptyLabel?: string;
    }>(),
    {
        previousBody: null,
        previousLabel: null,
        currentLabel: null,
        disabled: false,
        required: false,
        error: undefined,
        inputId: 'body',
        label: undefined,
        help: undefined,
        writeLabel: undefined,
        previewLabel: undefined,
        diffLabel: undefined,
        emptyLabel: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useTranslations();
const activeTab = ref('write');

const resolvedWriteLabel = computed(
    () => props.writeLabel ?? t('common.markdown.write'),
);
const resolvedPreviewLabel = computed(
    () => props.previewLabel ?? t('common.markdown.preview'),
);
const resolvedDiffLabel = computed(
    () => props.diffLabel ?? t('common.markdown.diff'),
);
const resolvedEmptyLabel = computed(
    () => props.emptyLabel ?? t('common.markdown.empty'),
);

const canDiff = computed(
    () =>
        props.previousBody !== null &&
        props.previousBody !== undefined &&
        props.previousBody !== '',
);

watch(canDiff, (ok) => {
    if (!ok && activeTab.value === 'diff') {
        activeTab.value = 'write';
    }
});

const textareaClass =
    'border-input bg-background flex min-h-48 w-full rounded-md border px-3 py-2 font-mono text-sm disabled:opacity-60';

const onInput = (event: Event): void => {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
};
</script>

<template>
    <div class="grid gap-2">
        <FieldLabel
            :html-for="inputId"
            :help="help ?? t('policies.help.body')"
            :required="required"
        >
            {{ label ?? t('policies.fields.body') }}
        </FieldLabel>

        <Tabs v-model="activeTab" class="w-full">
            <TabsList class="w-full sm:w-fit">
                <TabsTrigger value="write" class="flex-1 sm:flex-none">
                    {{ resolvedWriteLabel }}
                </TabsTrigger>
                <TabsTrigger value="preview" class="flex-1 sm:flex-none">
                    {{ resolvedPreviewLabel }}
                </TabsTrigger>
                <TabsTrigger
                    v-if="canDiff"
                    value="diff"
                    class="flex-1 sm:flex-none"
                >
                    {{ resolvedDiffLabel }}
                </TabsTrigger>
            </TabsList>

            <TabsContent value="write" class="mt-3">
                <textarea
                    :id="inputId"
                    :value="modelValue"
                    rows="16"
                    :required="required"
                    :disabled="disabled"
                    :class="textareaClass"
                    @input="onInput"
                />
            </TabsContent>

            <TabsContent value="preview" class="mt-3">
                <MarkdownPreview
                    :source="modelValue"
                    :empty-label="resolvedEmptyLabel"
                />
            </TabsContent>

            <TabsContent v-if="canDiff" value="diff" class="mt-3">
                <TextDiffViewer
                    :previous="previousBody ?? ''"
                    :current="modelValue"
                    :previous-label="
                        previousLabel ?? t('policies.diff_previous')
                    "
                    :current-label="currentLabel ?? t('policies.diff_current')"
                />
            </TabsContent>
        </Tabs>

        <InputError :message="error" />
    </div>
</template>
