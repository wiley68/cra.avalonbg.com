<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <p
        data-slot="panel-title"
        :class="cn('mb-3 text-sm font-semibold', props.class)"
    >
        <slot />
    </p>
</template>
