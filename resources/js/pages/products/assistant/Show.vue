<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bot, Send } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { show as assistantShow } from '@/routes/products/assistant';
import { store as storeAssistantMessage } from '@/routes/products/assistant/messages';

type OrganizationSummary = { id: number; name: string; slug: string };
type ProductSummary = { id: number; name: string; slug: string };

type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string | null;
};

type ConversationPayload = {
    id: number;
    context_type: string;
    messages: ChatMessage[];
} | null;

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    ai_enabled: boolean;
    provider: string;
    conversation: ConversationPayload;
}>();

const { t } = useTranslations();
const { backHref } = useProductModuleBack(props.product.id);

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'breadcrumbs.assistant',
        href: assistantShow(props.product.id),
    },
]);

const form = useForm({
    content: '',
});

const messagesEnd = ref<HTMLElement | null>(null);

const chatMessages = computed(() => props.conversation?.messages ?? []);

const canSend = computed(
    () =>
        props.ai_enabled && form.content.trim().length > 0 && !form.processing,
);

const assistantError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.assistant ?? null;
});

async function scrollToBottom(): Promise<void> {
    await nextTick();
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
}

watch(
    () => props.conversation?.messages?.length,
    () => {
        void scrollToBottom();
    },
    { immediate: true },
);

function submit(): void {
    if (!canSend.value) {
        return;
    }

    form.post(storeAssistantMessage(props.product.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('content');
        },
    });
}
</script>

<template>
    <Head :title="t('products.assistant.title')" />

    <div class="flex min-h-[calc(100vh-8rem)] flex-col space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.assistant.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.assistant.subtitle') }}
                </p>
            </div>

            <Button as-child variant="outline">
                <Link :href="backHref">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <div
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            {{ t('products.assistant.disclaimer') }}
        </div>

        <div
            v-if="!props.ai_enabled"
            class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
        >
            {{ t('assistant.disabled') }}
        </div>

        <div
            v-else-if="props.provider === 'stub'"
            class="rounded-lg border px-4 py-2 text-sm text-muted-foreground"
        >
            {{ t('assistant.stub_provider_note') }}
        </div>

        <div
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border bg-background"
        >
            <div class="flex-1 space-y-4 overflow-y-auto p-4">
                <div
                    v-if="chatMessages.length === 0"
                    class="flex h-full min-h-48 flex-col items-center justify-center gap-2 text-center text-muted-foreground"
                >
                    <Bot class="h-8 w-8 opacity-50" />
                    <p class="text-sm">
                        {{ t('products.assistant.empty') }}
                    </p>
                </div>

                <div
                    v-for="message in chatMessages"
                    :key="message.id"
                    class="flex"
                    :class="
                        message.role === 'user'
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <div
                        class="max-w-[85%] rounded-lg px-3 py-2 text-sm whitespace-pre-wrap"
                        :class="
                            message.role === 'user'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-foreground'
                        "
                    >
                        <p class="mb-1 text-xs font-medium opacity-70">
                            {{
                                message.role === 'user'
                                    ? t('products.assistant.role_user')
                                    : t('products.assistant.role_assistant')
                            }}
                        </p>
                        {{ message.content }}
                    </div>
                </div>
                <div ref="messagesEnd" />
            </div>

            <form class="border-t p-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label class="sr-only" for="assistant-content">
                        {{ t('products.assistant.input_label') }}
                    </label>
                    <textarea
                        id="assistant-content"
                        v-model="form.content"
                        rows="3"
                        class="flex min-h-20 w-full flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        :placeholder="t('products.assistant.input_placeholder')"
                        :disabled="!props.ai_enabled || form.processing"
                        @keydown.enter.exact.prevent="submit"
                    />
                    <Button type="submit" :disabled="!canSend">
                        <Send class="h-4 w-4" />
                        {{ t('products.assistant.send') }}
                    </Button>
                </div>
                <p
                    v-if="form.errors.content"
                    class="mt-2 text-sm text-destructive"
                >
                    {{ form.errors.content }}
                </p>
                <p
                    v-else-if="assistantError"
                    class="mt-2 text-sm text-destructive"
                >
                    {{ assistantError }}
                </p>
            </form>
        </div>
    </div>
</template>
