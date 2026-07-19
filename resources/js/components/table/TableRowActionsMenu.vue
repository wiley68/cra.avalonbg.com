<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';

export type TableRowAction = {
    label: string;
    icon?: Component;
    onSelect: () => void;
    variant?: 'default' | 'destructive';
    separatorAfter?: boolean;
    class?: string;
};

const props = withDefaults(
    defineProps<{
        actions: TableRowAction[];
        label?: string;
        triggerText?: string;
        triggerVariant?:
            | 'default'
            | 'destructive'
            | 'outline'
            | 'secondary'
            | 'ghost'
            | 'link';
    }>(),
    {
        label: undefined,
        triggerText: undefined,
        triggerVariant: 'ghost',
    },
);

const { t } = useTranslations();

const menuLabel = computed(() => props.label ?? t('common.manage'));
const showTextTrigger = computed(() => Boolean(props.triggerText));
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                :variant="
                    showTextTrigger
                        ? (props.triggerVariant ?? 'outline')
                        : 'ghost'
                "
                :class="
                    showTextTrigger
                        ? 'inline-flex items-center gap-2'
                        : 'h-8 w-8 p-0 text-base leading-none'
                "
                type="button"
                :aria-label="menuLabel"
            >
                <template v-if="showTextTrigger">{{
                    props.triggerText
                }}</template>
                <template v-else>...</template>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuLabel>{{ menuLabel }}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <template v-for="action in actions" :key="action.label">
                <DropdownMenuItem
                    :variant="action.variant"
                    :class="action.class"
                    @click="action.onSelect"
                >
                    <component
                        :is="action.icon"
                        v-if="action.icon"
                        class="size-4"
                        :class="action.class"
                    />
                    {{ action.label }}
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="action.separatorAfter" />
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
