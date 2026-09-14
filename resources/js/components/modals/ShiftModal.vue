<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Wallet, Lock, Unlock, DollarSign, Calculator } from 'lucide-vue-next';

interface ShiftSummary {
    shift_id: number;
    cashier_name: string;
    started_at: string;
    opening_float_lyd: number;
    cash_collected_lyd: number;
    card_collected_lyd: number;
    expected_current_cash_lyd: number;
    total_revenue_lyd: number;
    completed_sessions_count: number;
}

const props = defineProps<{
    show: boolean;
    activeShift: ShiftSummary | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const mode = ref<'summary' | 'open' | 'close'>('summary');

// Open Shift Form
const openForm = useForm({
    opening_float_millimes: 150000, // 150.000 LYD default
    notes: '',
});
const openingFloatLyd = ref<number>(150);

// Close Shift Form
const closeForm = useForm({
    closing_cash_counted_millimes: 0,
    notes: '',
});
const cashCountedLyd = ref<number>(0);

const calculatedDifferenceLyd = computed(() => {
    if (!props.activeShift) return 0;
    return (cashCountedLyd.value || 0) - props.activeShift.expected_current_cash_lyd;
});

function submitOpen() {
    openForm.opening_float_millimes = Math.round(openingFloatLyd.value * 1000);
    openForm.post('/shift/open', {
        preserveScroll: true,
        onSuccess: () => {
            mode.value = 'summary';
            emit('close');
        },
    });
}

function submitClose() {
    closeForm.closing_cash_counted_millimes = Math.round(cashCountedLyd.value * 1000);
    closeForm.post('/shift/close', {
        preserveScroll: true,
        onSuccess: () => {
            mode.value = 'summary';
            emit('close');
        },
    });
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-lg bg-surface-elevated border border-surface-border rounded-2xl shadow-xl overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-surface-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Wallet class="w-5 h-5 text-text-muted" />
                    <h3 class="text-base font-semibold text-text-primary">
                        Shift & Cash Drawer Management
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-text-muted hover:text-text-primary transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 flex flex-col gap-5">
                <!-- 1. SUMMARY VIEW (If Shift Open) -->
                <template v-if="activeShift && mode === 'summary'">
                    <div class="p-4 rounded-xl bg-surface-overlay border border-surface-border-subtle flex flex-col gap-3 font-sans text-xs">
                        <div class="flex justify-between items-center pb-2 border-b border-surface-border-subtle">
                            <span class="font-semibold text-text-primary text-sm">Active Shift: {{ activeShift.cashier_name }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-status-available/15 text-status-available font-semibold border border-status-available/40 text-xs">
                                OPEN
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                            <div class="p-2.5 rounded-lg bg-surface-overlay border border-surface-border-subtle">
                                <span class="text-text-muted block">Opening Float:</span>
                                <span class="text-base font-semibold text-text-primary font-mono tabular-nums">{{ Math.round(activeShift.opening_float_lyd) }} LYD</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-surface-overlay border border-surface-border-subtle">
                                <span class="text-text-muted block">Cash Collected:</span>
                                <span class="text-base font-semibold text-status-available font-mono tabular-nums">+{{ Math.round(activeShift.cash_collected_lyd) }} LYD</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-surface-overlay border border-surface-border-subtle">
                                <span class="text-text-muted block">Completed Sessions:</span>
                                <span class="text-base font-semibold text-text-primary font-mono tabular-nums">{{ activeShift.completed_sessions_count }}</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-status-warning/10 border border-status-warning/30 flex justify-between items-center mt-2">
                            <span class="font-semibold text-status-warning text-sm">Expected Cash in Drawer:</span>
                            <span class="text-xl font-semibold font-mono tabular-nums text-status-warning">{{ Math.round(activeShift.expected_current_cash_lyd) }} LYD</span>
                        </div>
                    </div>

                    <!-- Close Shift Trigger -->
                    <button
                        @click="mode = 'close'"
                        type="button"
                        class="w-full py-3 px-4 rounded-lg bg-status-rogue hover:brightness-110 text-text-primary font-semibold text-sm transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <Lock class="w-4 h-4" /> Close Shift (Blind Cash Drop)
                    </button>
                </template>

                <!-- 2. CLOSE SHIFT BLIND DROP VIEW -->
                <template v-else-if="mode === 'close'">
                    <div class="flex flex-col gap-4">
                        <div class="p-3.5 rounded-xl bg-surface-overlay border border-surface-border-subtle text-xs text-text-secondary">
                            <p class="font-semibold text-text-primary mb-1">Blind Cash Drop Reconciliation:</p>
                            <p>Count all physical Libyan Dinar cash currently present in the drawer and enter the sum below.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-1">
                                Physical Cash Counted (LYD)
                            </label>
                            <input
                                v-model.number="cashCountedLyd"
                                type="number"
                                step="5"
                                min="0"
                                class="w-full px-3.5 py-3 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-xl font-semibold focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                placeholder="0"
                            />
                        </div>

                        <div class="p-3.5 rounded-xl bg-surface-overlay border border-surface-border-subtle flex justify-between items-center text-xs">
                            <span class="text-text-muted">Cash Difference (Discrepancy):</span>
                            <span
                                :class="[
                                    'font-mono text-base font-semibold tabular-nums',
                                    calculatedDifferenceLyd === 0 ? 'text-text-muted' : (calculatedDifferenceLyd > 0 ? 'text-status-available' : 'text-status-rogue')
                                ]"
                            >
                                {{ calculatedDifferenceLyd > 0 ? '+' : '' }}{{ Math.round(calculatedDifferenceLyd) }} LYD
                                <span v-if="calculatedDifferenceLyd === 0"> (Balanced)</span>
                                <span v-else-if="calculatedDifferenceLyd > 0"> (Surplus)</span>
                                <span v-else> (Deficit)</span>
                            </span>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button
                                @click="mode = 'summary'"
                                type="button"
                                class="flex-1 py-3 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle font-semibold text-xs transition cursor-pointer"
                            >
                                Back
                            </button>
                            <button
                                @click="submitClose"
                                type="button"
                                class="flex-2 py-3 rounded-lg bg-status-rogue hover:brightness-110 text-text-primary font-semibold text-sm transition cursor-pointer"
                            >
                                Confirm & End Shift
                            </button>
                        </div>
                    </div>
                </template>

                <!-- 3. OPEN NEW SHIFT VIEW -->
                <template v-else>
                    <div class="flex flex-col gap-4">
                        <div class="p-3.5 rounded-xl bg-surface-overlay border border-surface-border-subtle text-xs text-text-secondary">
                            <p class="font-semibold text-text-primary mb-1">Open Cashier Shift:</p>
                            <p>Enter the opening cash float placed into the drawer at shift start.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-1">
                                Opening Float Amount (LYD)
                            </label>
                            <input
                                v-model.number="openingFloatLyd"
                                type="number"
                                step="10"
                                min="0"
                                class="w-full px-3.5 py-3 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-xl font-semibold focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                placeholder="150.000"
                            />
                        </div>

                        <button
                            @click="submitOpen"
                            type="button"
                            class="w-full py-3.5 px-4 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary font-semibold text-sm transition flex items-center justify-center gap-2 cursor-pointer mt-2"
                        >
                            <Unlock class="w-4 h-4" /> Open New Shift
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
