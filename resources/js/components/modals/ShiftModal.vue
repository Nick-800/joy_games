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
        <div class="w-full max-w-lg bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 bg-[#1e293b]/70 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Wallet class="w-5 h-5 text-amber-400" />
                    <h3 class="text-lg font-bold text-white">
                        Shift & Cash Drawer Management
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 flex flex-col gap-5">
                <!-- 1. SUMMARY VIEW (If Shift Open) -->
                <template v-if="activeShift && mode === 'summary'">
                    <div class="p-4 rounded-xl bg-[#090d16] border border-slate-800 flex flex-col gap-3 font-sans text-xs">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                            <span class="font-bold text-white text-sm">Active Shift: {{ activeShift.cashier_name }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/40 text-[10px]">
                                OPEN
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
                                <span class="text-slate-400 block">Opening Float:</span>
                                <span class="text-base font-bold text-white font-mono">{{ activeShift.opening_float_lyd.toFixed(3) }} LYD</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
                                <span class="text-slate-400 block">Cash Collected:</span>
                                <span class="text-base font-bold text-emerald-400 font-mono">+{{ activeShift.cash_collected_lyd.toFixed(3) }} LYD</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
                                <span class="text-slate-400 block">Card / Digital:</span>
                                <span class="text-base font-bold text-sky-400 font-mono">{{ activeShift.card_collected_lyd.toFixed(3) }} LYD</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
                                <span class="text-slate-400 block">Completed Sessions:</span>
                                <span class="text-base font-bold text-purple-300 font-mono">{{ activeShift.completed_sessions_count }}</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 flex justify-between items-center mt-2">
                            <span class="font-bold text-amber-300 text-sm">Expected Cash in Drawer:</span>
                            <span class="text-xl font-bold font-mono text-amber-200">{{ activeShift.expected_current_cash_lyd.toFixed(3) }} LYD</span>
                        </div>
                    </div>

                    <!-- Close Shift Trigger -->
                    <button
                        @click="mode = 'close'"
                        type="button"
                        class="w-full py-3 px-4 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <Lock class="w-4 h-4" /> Close Shift (Blind Cash Drop)
                    </button>
                </template>

                <!-- 2. CLOSE SHIFT BLIND DROP VIEW -->
                <template v-else-if="mode === 'close'">
                    <div class="flex flex-col gap-4">
                        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300">
                            <p class="font-bold text-white mb-1">Blind Cash Drop Reconciliation:</p>
                            <p>Count all physical Libyan Dinar cash currently present in the drawer and enter the sum below.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                                Physical Cash Counted (LYD)
                            </label>
                            <input
                                v-model.number="cashCountedLyd"
                                type="number"
                                step="1"
                                min="0"
                                class="w-full px-3.5 py-3 bg-[#090d16] border border-slate-700 rounded-xl text-white font-mono text-xl font-bold focus:outline-none focus:border-amber-500"
                                placeholder="0.000"
                            />
                        </div>

                        <div class="p-3.5 rounded-xl bg-[#090d16] border border-slate-800 flex justify-between items-center text-xs">
                            <span class="text-slate-400">Cash Difference (Discrepancy):</span>
                            <span
                                :class="[
                                    'font-mono text-base font-bold',
                                    calculatedDifferenceLyd === 0 ? 'text-emerald-400' : (calculatedDifferenceLyd > 0 ? 'text-sky-400' : 'text-rose-400')
                                ]"
                            >
                                {{ calculatedDifferenceLyd > 0 ? '+' : '' }}{{ calculatedDifferenceLyd.toFixed(3) }} LYD
                                <span v-if="calculatedDifferenceLyd === 0"> (Balanced)</span>
                                <span v-else-if="calculatedDifferenceLyd > 0"> (Surplus)</span>
                                <span v-else> (Deficit)</span>
                            </span>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button
                                @click="mode = 'summary'"
                                type="button"
                                class="flex-1 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition cursor-pointer"
                            >
                                Back
                            </button>
                            <button
                                @click="submitClose"
                                type="button"
                                class="flex-2 py-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-sm shadow-md transition cursor-pointer"
                            >
                                Confirm & End Shift
                            </button>
                        </div>
                    </div>
                </template>

                <!-- 3. OPEN NEW SHIFT VIEW -->
                <template v-else>
                    <div class="flex flex-col gap-4">
                        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300">
                            <p class="font-bold text-white mb-1">Open Cashier Shift:</p>
                            <p>Enter the opening cash float placed into the drawer at shift start.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                                Opening Float Amount (LYD)
                            </label>
                            <input
                                v-model.number="openingFloatLyd"
                                type="number"
                                step="10"
                                min="0"
                                class="w-full px-3.5 py-3 bg-[#090d16] border border-slate-700 rounded-xl text-white font-mono text-xl font-bold focus:outline-none focus:border-sky-500"
                                placeholder="150.000"
                            />
                        </div>

                        <button
                            @click="submitOpen"
                            type="button"
                            class="w-full py-3.5 px-4 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer mt-2"
                        >
                            <Unlock class="w-4 h-4" /> Open New Shift
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
