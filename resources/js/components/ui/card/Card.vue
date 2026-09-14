<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'rounded-2xl border border-surface-border-subtle bg-surface-card text-text-primary shadow-sm transition-all duration-200 hover:border-surface-border/80',
                props.class
            )
        "
    >
        <slot />
    </div>
</template>
