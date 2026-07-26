<script setup lang="ts">
import { computed, provide, ref, type ComputedRef } from 'vue';

export type PageFormHeaderRegistration = {
    register: () => void;
    unregister: () => void;
};

const actionCount = ref(0);

const compact = computed(() => actionCount.value > 2);

provide<PageFormHeaderRegistration>('pageFormHeaderRegistration', {
    register: () => {
        actionCount.value += 1;
    },
    unregister: () => {
        actionCount.value = Math.max(0, actionCount.value - 1);
    },
});

provide<ComputedRef<boolean>>('pageFormHeaderCompact', compact);
</script>

<template>
    <div
        class="flex min-w-0 flex-wrap items-center justify-start gap-2 sm:justify-end"
    >
        <slot />
    </div>
</template>
