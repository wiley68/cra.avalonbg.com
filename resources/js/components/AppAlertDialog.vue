<script setup lang="ts">
import { computed } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslations } from '@/composables/useTranslations';

const open = defineModel<boolean>('open', { required: true });

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        mode?: 'confirm' | 'info';
        variant?: 'default' | 'destructive';
        confirmLabel?: string;
        cancelLabel?: string;
        loading?: boolean;
    }>(),
    {
        description: undefined,
        mode: 'confirm',
        variant: 'destructive',
        confirmLabel: undefined,
        cancelLabel: undefined,
        loading: false,
    },
);

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

const { t } = useTranslations();

const resolvedCancelLabel = computed(
    () => props.cancelLabel ?? t('common.cancel'),
);

const resolvedConfirmLabel = computed(() => {
    if (props.confirmLabel) {
        return props.confirmLabel;
    }

    if (props.mode === 'info') {
        return t('common.close');
    }

    return props.variant === 'destructive'
        ? t('common.confirm_delete')
        : t('common.confirm');
});

const confirmActionClass = computed(() =>
    props.variant === 'destructive'
        ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90'
        : undefined,
);

const handleConfirm = () => {
    emit('confirm');
};

const handleCancel = () => {
    emit('cancel');
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="description || $slots.description">
                    <slot name="description">
                        {{ description }}
                    </slot>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel
                    v-if="mode === 'confirm'"
                    :disabled="loading"
                    @click="handleCancel"
                >
                    {{ resolvedCancelLabel }}
                </AlertDialogCancel>
                <AlertDialogAction
                    :class="confirmActionClass"
                    :disabled="loading"
                    @click.prevent="handleConfirm"
                >
                    {{
                        loading
                            ? `${resolvedConfirmLabel}…`
                            : resolvedConfirmLabel
                    }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
