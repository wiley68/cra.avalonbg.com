<script setup lang="ts">
import PageFormHeaderActions from '@/components/PageFormHeaderActions.vue';
</script>

<template>
    <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
        <div class="min-w-0 space-y-1">
            <slot />
        </div>
        <PageFormHeaderActions>
            <slot name="actions" />
        </PageFormHeaderActions>
    </div>
</template>
