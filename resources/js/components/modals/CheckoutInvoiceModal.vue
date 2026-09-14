<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X, Receipt, Banknote, CheckCircle2 } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import type { StationData } from '../StationCard.vue';

const props = defineProps<{
    show: boolean;
    station: StationData | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const discountLyd = ref<number>(0);
const cashReceivedLyd = ref<number>(0);
const notes = ref<string>('');

const session = computed(() => props.station?.active_session);

const timeSubtotalLyd = computed(() => session.value?.time_amount_lyd ?? 0);
const upfrontPaidLyd = computed(() => session.value?.upfront_paid_lyd ?? 0);

const grossTotalLyd = computed(() => {
    return timeSubtotalLyd.value;
});

const remainingTotalDueLyd = computed(() => {
    const raw = Math.max(0, grossTotalLyd.value - upfrontPaidLyd.value - (discountLyd.value || 0));

    return raw > 0 ? Math.ceil(raw / 5) * 5 : 0;
});

const changeDueLyd = computed(() => {
    return Math.max(0, (cashReceivedLyd.value || 0) - remainingTotalDueLyd.value);
});

const cashPresets = [5, 10, 20];

// Auto-fill cash received when modal opens or total changes
watch(() => remainingTotalDueLyd.value, (newDue) => {
    if (newDue === 0) {
        cashReceivedLyd.value = 0;
    } else if (cashReceivedLyd.value === 0 || cashReceivedLyd.value < newDue) {
        cashReceivedLyd.value = newDue;
    }
}, { immediate: true });

function submit() {
    if (!session.value) {
return;
}

    const form = useForm({
        payment_method: 'cash',
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
                            <span class="text-text-muted">Slice {{ idx + 1 }}: {{ inv.pricing_tier }} ({{ inv.billable_minutes }}m @ {{ Math.round(inv.rate_per_hour_millimes/1000) }} LYD)</span>
                            <span class="font-semibold text-text-primary">{{ Math.round(inv.subtotal_millimes / 1000) }} LYD</span>
                        </div>
                    </div>

                    <!-- Subtotals & Final Total -->
                    <div class="pt-3 border-t border-surface-border flex flex-col gap-1.5 font-sans">
                        <div class="flex justify-between text-text-muted">
                            <span>Time Subtotal:</span>
                            <span class="font-mono text-text-primary font-semibold tabular-nums">{{ Math.round(timeSubtotalLyd) }} LYD</span>
                        </div>
                        <div v-if="upfrontPaidLyd > 0" class="flex justify-between text-status-available font-semibold">
                            <span>Paid Upfront:</span>
                            <span class="font-mono tabular-nums">-{{ Math.round(upfrontPaidLyd) }} LYD</span>
                        </div>
                        <div v-if="discountLyd > 0" class="flex justify-between text-text-muted">
                            <span>Discount Applied:</span>
                            <span class="font-mono text-text-secondary tabular-nums">-{{ Math.round(discountLyd) }} LYD</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 mt-1 border-t border-surface-border text-base font-semibold text-text-primary">
                            <span class="text-text-muted">{{ upfrontPaidLyd > 0 ? 'Remaining Due (LYD):' : 'Total Due (LYD):' }}</span>
                            <span class="text-2xl font-mono text-text-primary font-semibold tabular-nums">{{ remainingTotalDueLyd }} LYD</span>
                        </div>
                    </div>
                </div>

                <!-- Cash Collection Section (Only Cash, Card Removed) -->
                <div v-if="remainingTotalDueLyd > 0" class="p-4 rounded-xl bg-surface-overlay border border-surface-border-subtle flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold uppercase tracking-wider text-text-muted flex items-center gap-1.5">
                            <Banknote class="w-3.5 h-3.5" /> Cash Received (LYD)
                        </label>
                        <span class="text-[11px] font-medium text-text-muted bg-surface-canvas px-2 py-0.5 rounded border border-surface-border-subtle">
                            Cash Only
                        </span>
                    </div>

                    <!-- 3 Options: 5, 10, 20 -->
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="preset in cashPresets"
                            :key="preset"
                            type="button"
                            @click="cashReceivedLyd = preset"
                            :class="[
                                'py-2 text-xs font-mono font-semibold rounded-lg border text-center transition cursor-pointer',
                                cashReceivedLyd === preset
                                    ? 'bg-brand-primary text-text-primary border-transparent'
                                    : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                            ]"
                        >
                            {{ preset }} LYD
                        </button>
                    </div>

                    <!-- Custom Cash Input & Change Due -->
                    <div class="grid grid-cols-2 gap-3 items-center pt-2 border-t border-surface-border-subtle">
                        <div>
                            <input
                                v-model.number="cashReceivedLyd"
                                type="number"
                                step="5"
                                min="0"
                                class="w-full px-3 py-2 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-sm focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                placeholder="Exact Cash"
                            />
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-text-muted block">Change to Return:</span>
                            <span :class="['text-lg font-mono font-semibold tabular-nums', changeDueLyd >= 0 ? 'text-status-available' : 'text-status-rogue']">
                                {{ Math.round(changeDueLyd) }} LYD
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Already Paid Notice (if remaining due is 0) -->
                <div v-else class="p-4 rounded-xl bg-status-available/10 border border-status-available/30 text-xs text-status-available flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 shrink-0" />
                    <span>This session was already fully paid upfront ({{ Math.round(upfrontPaidLyd) }} LYD). No additional payment is due.</span>
                </div>

                <!-- Discount (Optional) -->
                <div v-if="remainingTotalDueLyd > 0 || discountLyd > 0" class="flex items-center gap-3">
                    <label class="text-xs font-medium text-text-muted whitespace-nowrap">Discount (LYD):</label>
                    <input
                        v-model.number="discountLyd"
                        type="number"
                        step="5"
                        min="0"
                        class="w-32 px-3 py-1.5 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-xs focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                        placeholder="0"
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
                    {{ remainingTotalDueLyd === 0 ? 'Complete & Clear Station' : 'Settle & Clear Station' }}
                </button>
            </div>
        </div>
    </div>
</template>
