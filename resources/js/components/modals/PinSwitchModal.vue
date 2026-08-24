<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, KeyRound, Delete } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    staffUsers: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
    }>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const pin = ref('');
const errorMessage = ref('');

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'C', '0', '⌫'];

function pressKey(key: string) {
    errorMessage.value = '';
    if (key === 'C') {
        pin.value = '';
    } else if (key === '⌫') {
        pin.value = pin.value.slice(0, -1);
    } else if (pin.value.length < 4) {
        pin.value += key;
        if (pin.value.length === 4) {
            submit();
        }
    }
}

function submit() {
    const form = useForm({
        pin_code: pin.value,
    });

    form.post('/shift/switch-cashier', {
        preserveScroll: true,
        onError: (errors) => {
            errorMessage.value = errors.pin_code || 'Invalid PIN code';
            pin.value = '';
        },
        onSuccess: () => {
            pin.value = '';
            emit('close');
        },
    });
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-xs bg-surface-elevated border border-surface-border rounded-2xl shadow-xl overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-5 py-3.5 border-b border-surface-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <KeyRound class="w-4 h-4 text-text-muted" />
                    <h3 class="text-base font-semibold text-text-primary">
                        Enter 4-Digit Staff PIN
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-text-muted hover:text-text-primary transition cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="p-6 flex flex-col items-center gap-4">
                <!-- PIN Dots -->
                <div class="flex items-center gap-3 my-1">
                    <div
                        v-for="i in 4"
                        :key="i"
                        :class="[
                            'w-4 h-4 rounded-full border transition-all duration-150',
                            pin.length >= i
                                ? 'bg-brand-primary border-brand-primary'
                                : 'bg-surface-overlay border-surface-border'
                        ]"
                    ></div>
                </div>

                <p v-if="errorMessage" class="text-xs font-semibold text-status-rogue text-center">
                    {{ errorMessage }}
                </p>
                <p v-else class="text-xs text-text-muted text-center">
                    Default PINs: Nick (1234), Admin (9999)
                </p>

                <!-- Numeric Keypad Grid -->
                <div class="grid grid-cols-3 gap-2.5 w-full max-w-[220px]">
                    <button
                        v-for="key in keys"
                        :key="key"
                        @click="pressKey(key)"
                        type="button"
                        class="h-12 rounded-lg bg-surface-elevated hover:bg-surface-border active:bg-surface-border border border-surface-border-subtle text-text-primary font-mono text-lg font-semibold flex items-center justify-center transition cursor-pointer select-none"
                    >
                        <Delete v-if="key === '⌫'" class="w-5 h-5 text-text-muted" />
                        <span v-else>{{ key }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
