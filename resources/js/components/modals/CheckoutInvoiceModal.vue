<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Receipt, CreditCard, Banknote, CheckCircle2, Calculator } from 'lucide-vue-next';
import type { StationData } from '../StationCard.vue';

const props = defineProps<{
    show: boolean;
    station: StationData | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const paymentMethod = ref<'cash' | 'card' | 'split'>('cash');
const discountLyd = ref<number>(0);
const cashReceivedLyd = ref<number>(0);
const notes = ref<string>('');

const session = computed(() => props.station?.active_session);

const timeSubtotalLyd = computed(() => session.value?.time_amount_lyd ?? 0);
const retailSubtotalLyd = computed(() => session.value?.retail_amount_lyd ?? 0);

const finalTotalLyd = computed(() => {
    const gross = timeSubtotalLyd.value + retailSubtotalLyd.value;
    return Math.max(0, gross - (discountLyd.value || 0));
});

const changeDueLyd = computed(() => {
    if (paymentMethod.value !== 'cash') return 0;
    return Math.max(0, (cashReceivedLyd.value || 0) - finalTotalLyd.value);
});

const cashPresets = [10, 20, 30, 40, 50, 100];

// Auto-fill cash received when modal opens or total changes
watch(() => finalTotalLyd.value, (newTotal) => {
    if (cashReceivedLyd.value === 0 || cashReceivedLyd.value < newTotal) {
        // Nearest higher preset or exact
        const rounded = Math.ceil(newTotal / 10) * 10;
        cashReceivedLyd.value = Math.max(newTotal, rounded);
    }
}, { immediate: true });

function submit() {
    if (!session.value) return;

    const form = useForm({
        payment_method: paymentMethod.value,
        cash_received_millimes: Math.round(cashReceivedLyd.value * 1000),
        discount_millimes: Math.round(discountLyd.value * 1000),
        notes: notes.value,
    });

    form.post(`/sessions/${session.value.id}/settle`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}
</script>

<template>
    <div v-if="show && station && session" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-xl bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-6 py-4 bg-[#1e293b]/70 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Receipt class="w-5 h-5 text-amber-400" />
                    <h3 class="text-lg font-bold text-white">
                        Checkout & Invoice — {{ station.name }}
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

            <!-- Receipt Body -->
            <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-5">
                <!-- Itemized Receipt Card -->
                <div class="p-4 rounded-xl bg-[#090d16] border border-slate-800 flex flex-col gap-3 font-mono text-xs">
                    <div class="flex justify-between items-center pb-2 border-b border-slate-800 font-sans">
                        <span class="font-bold text-white uppercase tracking-wider text-xs">Itemized Breakdown</span>
                        <span class="text-slate-400 text-xs">Ref #{{ session.id }}</span>
                    </div>

                    <!-- Time Intervals -->
                    <div>
                        <span class="font-bold text-sky-400 font-sans block mb-1.5 uppercase text-[11px]">🎮 Game Time Intervals:</span>
                        <div
                            v-for="(inv, idx) in session.intervals"
                            :key="inv.id"
                            class="flex justify-between py-1 text-slate-300"
                        >
                            <span>Slice {{ idx + 1 }}: {{ inv.pricing_tier }} ({{ inv.billable_minutes }}m @ {{ (inv.rate_per_hour_millimes/1000).toFixed(3) }} LYD)</span>
                            <span class="font-bold text-white">{{ (inv.subtotal_millimes / 1000).toFixed(3) }} LYD</span>
                        </div>
                    </div>

                    <!-- Retail Items -->
                    <div v-if="session.order_items?.length" class="pt-2 border-t border-slate-800/80">
                        <span class="font-bold text-purple-400 font-sans block mb-1.5 uppercase text-[11px]">🥤 Retail Snacks / Drinks:</span>
                        <div
                            v-for="item in session.order_items"
                            :key="item.id"
                            class="flex justify-between py-1 text-slate-300"
                        >
                            <span>{{ item.quantity }}x {{ item.item_name }}</span>
                            <span class="font-bold text-white">{{ item.subtotal_lyd.toFixed(3) }} LYD</span>
                        </div>
                    </div>

                    <!-- Subtotals & Final Total -->
                    <div class="pt-3 border-t border-slate-700 flex flex-col gap-1.5 font-sans">
                        <div class="flex justify-between text-slate-400">
                            <span>Time Subtotal:</span>
                            <span class="font-mono text-white">{{ timeSubtotalLyd.toFixed(3) }} LYD</span>
                        </div>
                        <div v-if="retailSubtotalLyd > 0" class="flex justify-between text-slate-400">
                            <span>Retail Add-ons:</span>
                            <span class="font-mono text-white">{{ retailSubtotalLyd.toFixed(3) }} LYD</span>
                        </div>
                        <div v-if="discountLyd > 0" class="flex justify-between text-rose-400">
                            <span>Discount Applied:</span>
                            <span class="font-mono">-{{ discountLyd.toFixed(3) }} LYD</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 mt-1 border-t border-slate-700 text-base font-bold text-white">
                            <span class="text-amber-400">Total Due (LYD):</span>
                            <span class="text-2xl font-mono text-amber-300 font-extrabold">{{ finalTotalLyd.toFixed(3) }} LYD</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selector -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Select Payment Method
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="paymentMethod = 'cash'"
                            :class="[
                                'py-3 px-4 rounded-xl border text-sm font-bold flex items-center justify-center gap-2 transition cursor-pointer',
                                paymentMethod === 'cash'
                                    ? 'bg-emerald-600/20 border-emerald-500 text-emerald-300 ring-1 ring-emerald-500'
                                    : 'bg-slate-900 border-slate-800 text-slate-400'
                            ]"
                        >
                            <Banknote class="w-4 h-4" /> Cash Drawer
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'card'"
                            :class="[
                                'py-3 px-4 rounded-xl border text-sm font-bold flex items-center justify-center gap-2 transition cursor-pointer',
                                paymentMethod === 'card'
                                    ? 'bg-sky-600/20 border-sky-500 text-sky-300 ring-1 ring-sky-500'
                                    : 'bg-slate-900 border-slate-800 text-slate-400'
                            ]"
                        >
                            <CreditCard class="w-4 h-4" /> POS Card / Digital
                        </button>
                    </div>
                </div>

                <!-- Cash Calculator (if Cash) -->
                <div v-if="paymentMethod === 'cash'" class="p-4 rounded-xl bg-[#090d16] border border-emerald-500/30 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                            <Calculator class="w-3.5 h-3.5" /> Cash Received (LYD)
                        </label>
                    </div>

                    <!-- Presets -->
                    <div class="grid grid-cols-6 gap-1.5">
                        <button
                            v-for="preset in cashPresets"
                            :key="preset"
                            type="button"
                            @click="cashReceivedLyd = preset"
                            :class="[
                                'py-1.5 text-xs font-mono font-bold rounded-lg border text-center transition cursor-pointer',
                                cashReceivedLyd === preset
                                    ? 'bg-emerald-500 text-slate-950 border-emerald-400'
                                    : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-slate-700'
                            ]"
                        >
                            {{ preset }}
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 items-center pt-2 border-t border-slate-800">
                        <div>
                            <input
                                v-model.number="cashReceivedLyd"
                                type="number"
                                step="0.5"
                                min="0"
                                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-emerald-500"
                                placeholder="Exact Cash"
                            />
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400 block">Change to Return:</span>
                            <span :class="['text-lg font-mono font-bold', changeDueLyd >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                                {{ changeDueLyd.toFixed(3) }} LYD
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Discount (Optional) -->
                <div class="flex items-center gap-3">
                    <label class="text-xs font-medium text-slate-400 whitespace-nowrap">Discount (LYD):</label>
                    <input
                        v-model.number="discountLyd"
                        type="number"
                        step="0.5"
                        min="0"
                        class="w-32 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white font-mono text-xs focus:outline-none focus:border-sky-500"
                        placeholder="0.000"
                    />
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-[#1e293b]/70 border-t border-slate-800 flex items-center justify-between">
                <button
                    @click="emit('close')"
                    type="button"
                    class="py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    @click="submit"
                    type="button"
                    class="py-3 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-lg shadow-emerald-600/30 transition flex items-center gap-2 cursor-pointer"
                >
                    <CheckCircle2 class="w-4 h-4" />
                    Complete & Clear Station
                </button>
            </div>
        </div>
    </div>
</template>
