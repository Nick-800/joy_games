<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <h3 :class="cn('text-xs font-semibold uppercase tracking-wider text-text-muted flex items-center justify-between', props.class)">
        <slot />
    </h3>
</template>
