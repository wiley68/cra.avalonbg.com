<script setup lang="ts">
import { computed, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    loading?: boolean;
    i18nPrefix?: string;
}>();

const emit = defineEmits<{
    confirm: [password: string, passwordConfirmation: string];
}>();

const { t } = useTranslations();

const translationPrefix = computed(() => props.i18nPrefix ?? 'users.export');

const password = ref('');
const passwordConfirmation = ref('');

watch(open, (isOpen) => {
    if (!isOpen) {
        password.value = '';
        passwordConfirmation.value = '';
    }
});

const handleConfirm = () => {
    emit('confirm', password.value, passwordConfirmation.value);
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t(`${translationPrefix}.dialog.title`)
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t(`${translationPrefix}.dialog.description`) }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="export-archive-password">{{
                        t(`${translationPrefix}.dialog.password`)
                    }}</Label>
                    <Input
                        id="export-archive-password"
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            t(
                                `${translationPrefix}.dialog.password_placeholder`,
                            )
                        "
                    />
                </div>

                <div class="space-y-2">
                    <Label for="export-archive-password-confirmation">{{
                        t(`${translationPrefix}.dialog.confirm_password`)
                    }}</Label>
                    <Input
                        id="export-archive-password-confirmation"
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            t(`${translationPrefix}.dialog.confirm_placeholder`)
                        "
                    />
                </div>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="props.loading">{{
                    t('common.cancel')
                }}</AlertDialogCancel>
                <AlertDialogAction
                    :disabled="props.loading"
                    @click.prevent="handleConfirm"
                >
                    {{
                        props.loading
                            ? t(`${translationPrefix}.exporting`)
                            : t(`${translationPrefix}.button`)
                    }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
