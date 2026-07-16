<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    title = '',
    description = '',
    titleKey = '',
    descriptionKey = '',
} = defineProps<{
    title?: string;
    description?: string;
    titleKey?: string;
    descriptionKey?: string;
}>();
</script>

<template>
    <AuthLayout
        :title="title"
        :description="description"
        :title-key="titleKey"
        :description-key="descriptionKey"
    >
        <slot />
    </AuthLayout>
</template>
