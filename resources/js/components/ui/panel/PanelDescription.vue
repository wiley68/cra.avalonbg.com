<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        data-slot="panel-description"
        :class="cn('text-sm opacity-80', props.class)"
    >
        <slot />
    </div>
</template>
