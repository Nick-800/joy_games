<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, AlertCircle, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface FlashProps {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
}

const page = usePage<{ flash?: FlashProps; errors?: Record<string, string> }>();

const visibleToast = ref<{ type: 'success' | 'error' | 'warning' | 'info'; message: string; key: number } | null>(null);
let counter = 0;
let timer: number | null = null;

function show(type: 'success' | 'error' | 'warning' | 'info', message: string) {
    if (timer) {
clearTimeout(timer);
}

    counter += 1;
    visibleToast.value = { type, message, key: counter };
    timer = window.setTimeout(() => {
        visibleToast.value = null;
    }, 4500);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) {
return;
}

        if (flash.success) {
show('success', flash.success);
} else if (flash.error) {
show('error', flash.error);
} else if (flash.warning) {
show('warning', flash.warning);
} else if (flash.info) {
show('info', flash.info);
}
    },
    { deep: true, immediate: true },
);

const firstError = computed(() => {
    const errors = page.props.errors;

    if (!errors || Object.keys(errors).length === 0) {
return null;
}

    return Object.values(errors)[0];
});

watch(firstError, (msg) => {
    if (msg) {
show('error', msg);
}
});

const styles = {
    success: { bar: 'bg-status-available', icon: CheckCircle2, iconClass: 'text-status-available' },
    error: { bar: 'bg-status-rogue', icon: XCircle, iconClass: 'text-status-rogue' },
    warning: { bar: 'bg-status-warning', icon: AlertCircle, iconClass: 'text-status-warning' },
    info: { bar: 'bg-brand-primary', icon: AlertCircle, iconClass: 'text-brand-primary' },
} as const;
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-if="visibleToast"
                :key="visibleToast.key"
                class="fixed top-4 left-1/2 -translate-x-1/2 z-[100] flex items-center gap-3 px-4 py-3 rounded-xl bg-surface-elevated border border-surface-border shadow-2xl max-w-md"
            >
                <span
                    :class="['w-1 self-stretch rounded-full', styles[visibleToast.type].bar]"
                />
                <component
                    :is="styles[visibleToast.type].icon"
                    :class="['w-4 h-4 shrink-0', styles[visibleToast.type].iconClass]"
                />
                <span class="text-sm text-text-primary flex-1">{{ visibleToast.message }}</span>
                <button
                    @click="visibleToast = null"
                    type="button"
                    class="text-text-muted hover:text-text-primary transition cursor-pointer"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
