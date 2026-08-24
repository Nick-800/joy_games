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
        <div class="w-full max-w-xl bg-surface-elevated border border-surface-border rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-surface-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Receipt class="w-5 h-5 text-text-muted" />
                    <h3 class="text-base font-semibold text-text-primary">
                        Checkout & Invoice — {{ station.name }}
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

            <!-- Receipt Body -->
            <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-5">
                <!-- Itemized Receipt Card -->
                <div class="p-4 rounded-xl bg-surface-overlay border border-surface-border-subtle flex flex-col gap-3 font-mono text-xs">
                    <div class="flex justify-between items-center pb-2 border-b border-surface-border-subtle font-sans">
                        <span class="font-semibold text-text-primary uppercase tracking-wider text-xs">Itemized Breakdown</span>
                        <span class="text-text-muted text-xs">Ref #{{ session.id }}</span>
                    </div>

                    <!-- Time Intervals -->
                    <div>
                        <span class="font-semibold text-text-secondary font-sans block mb-1.5 uppercase text-xs">Game Time Intervals:</span>
                        <div
                            v-for="(inv, idx) in session.intervals"
                            :key="inv.id"
                            class="flex justify-between py-1 font-mono tabular-nums"
                        >
                            <span class="text-text-muted">Slice {{ idx + 1 }}: {{ inv.pricing_tier }} ({{ inv.billable_minutes }}m @ {{ (inv.rate_per_hour_millimes/1000).toFixed(3) }} LYD)</span>
                            <span class="font-semibold text-text-primary">{{ (inv.subtotal_millimes / 1000).toFixed(3) }} LYD</span>
                        </div>
                    </div>

                    <!-- Retail Items -->
                    <div v-if="session.order_items?.length" class="pt-2 border-t border-surface-border-subtle">
                        <span class="font-semibold text-text-secondary font-sans block mb-1.5 uppercase text-xs">Retail Items:</span>
                        <div
                            v-for="item in session.order_items"
                            :key="item.id"
                            class="flex justify-between py-1 font-mono tabular-nums"
                        >
                            <span class="text-text-muted">{{ item.quantity }}x {{ item.item_name }}</span>
                            <span class="font-semibold text-text-primary">{{ item.subtotal_lyd.toFixed(3) }} LYD</span>
                        </div>
                    </div>

                    <!-- Subtotals & Final Total -->
                    <div class="pt-3 border-t border-surface-border flex flex-col gap-1.5 font-sans">
                        <div class="flex justify-between text-text-muted">
                            <span>Time Subtotal:</span>
                            <span class="font-mono text-text-primary font-semibold tabular-nums">{{ timeSubtotalLyd.toFixed(3) }} LYD</span>
                        </div>
                        <div v-if="retailSubtotalLyd > 0" class="flex justify-between text-text-muted">
                            <span>Retail Add-ons:</span>
                            <span class="font-mono text-text-primary font-semibold tabular-nums">{{ retailSubtotalLyd.toFixed(3) }} LYD</span>
                        </div>
                        <div v-if="discountLyd > 0" class="flex justify-between text-text-muted">
                            <span>Discount Applied:</span>
                            <span class="font-mono text-text-secondary tabular-nums">-{{ discountLyd.toFixed(3) }} LYD</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 mt-1 border-t border-surface-border text-base font-semibold text-text-primary">
                            <span class="text-text-muted">Total Due (LYD):</span>
                            <span class="text-2xl font-mono text-text-primary font-semibold tabular-nums">{{ finalTotalLyd.toFixed(3) }} LYD</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selector -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                        Select Payment Method
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="paymentMethod = 'cash'"
                            :class="[
                                'py-3 px-4 rounded-lg border text-sm font-semibold flex items-center justify-center gap-2 transition cursor-pointer',
                                paymentMethod === 'cash'
                                    ? 'bg-brand-primary text-text-primary border-transparent'
                                    : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                            ]"
                        >
                            <Banknote class="w-4 h-4" /> Cash Drawer
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'card'"
                            :class="[
                                'py-3 px-4 rounded-lg border text-sm font-semibold flex items-center justify-center gap-2 transition cursor-pointer',
                                paymentMethod === 'card'
                                    ? 'bg-brand-primary text-text-primary border-transparent'
                                    : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                            ]"
                        >
                            <CreditCard class="w-4 h-4" /> POS Card / Digital
                        </button>
                    </div>
                </div>

                <!-- Cash Calculator (if Cash) -->
                <div v-if="paymentMethod === 'cash'" class="p-4 rounded-xl bg-surface-overlay border border-surface-border-subtle flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold uppercase tracking-wider text-text-muted flex items-center gap-1.5">
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
                                'py-1.5 text-xs font-mono font-semibold rounded-lg border text-center transition cursor-pointer',
                                cashReceivedLyd === preset
                                    ? 'bg-brand-primary text-text-primary border-transparent'
                                    : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                            ]"
                        >
                            {{ preset }}
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 items-center pt-2 border-t border-surface-border-subtle">
                        <div>
                            <input
                                v-model.number="cashReceivedLyd"
                                type="number"
                                step="0.5"
                                min="0"
                                class="w-full px-3 py-2 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-sm focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                placeholder="Exact Cash"
                            />
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-text-muted block">Change to Return:</span>
                            <span :class="['text-lg font-mono font-semibold tabular-nums', changeDueLyd >= 0 ? 'text-status-available' : 'text-status-rogue']">
                                {{ changeDueLyd.toFixed(3) }} LYD
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Discount (Optional) -->
                <div class="flex items-center gap-3">
                    <label class="text-xs font-medium text-text-muted whitespace-nowrap">Discount (LYD):</label>
                    <input
                        v-model.number="discountLyd"
                        type="number"
                        step="0.5"
                        min="0"
                        class="w-32 px-3 py-1.5 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-xs focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                        placeholder="0.000"
                    />
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-surface-border-subtle flex items-center justify-between">
                <button
                    @click="emit('close')"
                    type="button"
                    class="py-2.5 px-4 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle font-semibold text-xs transition cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    @click="submit"
                    type="button"
                    class="py-3 px-6 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary font-semibold text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <CheckCircle2 class="w-4 h-4" />
                    Complete & Clear Station
                </button>
            </div>
        </div>
    </div>
</template>
