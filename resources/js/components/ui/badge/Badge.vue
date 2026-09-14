<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        variant?: 'default' | 'secondary' | 'outline' | 'success' | 'warning' | 'purple' | 'info';
        class?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'default',
    }
);

const variantStyles: Record<string, string> = {
    default: 'bg-brand-primary text-text-primary border-transparent',
    secondary: 'bg-surface-elevated text-text-secondary border-surface-border-subtle',
    outline: 'text-text-primary border-surface-border',
    success: 'bg-status-available/15 text-status-available border-status-available/30',
    warning: 'bg-status-warning/15 text-status-warning border-status-warning/30',
    purple: 'bg-status-postpaid/15 text-status-postpaid border-status-postpaid/30',
    info: 'bg-status-prepaid/15 text-status-prepaid border-status-prepaid/30',
};
</script>

<template>
    <div
        :class="
            cn(
                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors',
                variantStyles[props.variant],
                props.class
            )
        "
    >
        <slot />
    </div>
</template>
