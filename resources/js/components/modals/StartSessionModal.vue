<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Play, Clock, Zap, Users, User, ShieldCheck, Banknote, CheckCircle2 } from 'lucide-vue-next';
import type { StationData } from '../StationCard.vue';

const props = withDefaults(defineProps<{
    show: boolean;
    station: StationData | null;
    pricingTiers: Array<{
        id: number;
        name: string;
        controller_count_min: number;
        controller_count_max: number;
        hourly_rate_millimes: number;
        hourly_rate_lyd: number;
    }>;
    initialBackdateMinutes?: number;
    tvControlEnabled?: boolean;
}>(), {
    tvControlEnabled: false,
});

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const form = useForm({
    station_id: 0,
    session_type: 'prepaid' as 'prepaid' | 'postpaid',
    prepaid_payment_timing: 'before' as 'before' | 'after',
    pricing_tier_id: 0,
    allocated_minutes: 60,
    cash_received_lyd: 0,
    customer_name: '',
    customer_phone: '',
    auto_wake: true,
    allow_overtime: true,
    backdate_minutes: 0,
});

const durationPresets = [
    { label: '30 min', minutes: 30 },
    { label: '1 hour', minutes: 60 },
    { label: '1.5 hrs', minutes: 90 },
    { label: '2 hours', minutes: 120 },
    { label: '3 hours', minutes: 180 },
];

const cashPresets = [5, 10, 20];

function initialize() {
    if (!props.station) return;
    form.station_id = props.station.id;
    form.pricing_tier_id = props.pricingTiers[0]?.id ?? 1;
    form.session_type = 'prepaid';
    form.prepaid_payment_timing = 'before';
    form.allocated_minutes = 60;
    form.cash_received_lyd = 0;
    form.customer_name = '';
    form.customer_phone = '';
    form.auto_wake = true;
    form.allow_overtime = true;
    form.backdate_minutes = props.initialBackdateMinutes ?? 0;
}

watch(
    [() => props.show, () => props.station, () => props.initialBackdateMinutes],
    ([newShow, newStation]) => {
        if (newShow && newStation) {
            initialize();
        }
    },
    { immediate: true }
);

const selectedTier = computed(() => {
    return props.pricingTiers.find(t => t.id === form.pricing_tier_id);
});

function getTierRateLyd(tier: {
    id: number;
    controller_count_min: number;
    controller_count_max: number;
    hourly_rate_lyd: number;
}): number {
    if (!props.station) return tier.hourly_rate_lyd;

    if (tier.controller_count_max <= 2 && props.station.hourly_rate_1_2_lyd) {
        return props.station.hourly_rate_1_2_lyd;
    }
    if (tier.controller_count_min >= 3 && props.station.hourly_rate_3_4_lyd) {
        return props.station.hourly_rate_3_4_lyd;
    }
    if (props.station.default_hourly_rate_lyd) {
        return props.station.default_hourly_rate_lyd;
    }

    const mult = props.station.is_vip ? 1.50 : 1.00;
    const raw = tier.hourly_rate_lyd * mult;
    return Math.ceil(raw / 5) * 5;
}

const estimatedPrepaidCostLyd = computed(() => {
    if (form.session_type !== 'prepaid' || !selectedTier.value || !form.allocated_minutes) {
        return 0;
    }
    const rate = getTierRateLyd(selectedTier.value);
    const rawCost = (form.allocated_minutes / 60) * rate;
    if (rawCost <= 0) return 0;
    return Math.ceil(rawCost / 5) * 5;
});

watch(() => estimatedPrepaidCostLyd.value, (newCost) => {
    if (form.prepaid_payment_timing === 'before') {
        if (form.cash_received_lyd === 0 || form.cash_received_lyd < newCost) {
            form.cash_received_lyd = newCost;
        }
    }
}, { immediate: true });

const changeDueLyd = computed(() => {
    if (form.prepaid_payment_timing !== 'before') return 0;
    return Math.max(0, (form.cash_received_lyd || 0) - estimatedPrepaidCostLyd.value);
});

function submit() {
    form.transform((data) => ({
        ...data,
        cash_received_millimes: (data.session_type === 'prepaid' && data.prepaid_payment_timing === 'before')
            ? Math.round((data.cash_received_lyd || 0) * 1000)
            : null,
    })).post('/sessions/start', {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}
</script>

<template>
    <div v-if="show && station" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-lg bg-surface-elevated border border-surface-border rounded-2xl shadow-xl overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-surface-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-status-prepaid"></span>
                    <h3 class="text-base font-semibold text-text-primary">
                        Start Session — {{ station.name }}
                    </h3>
                    <span v-if="station.is_vip" class="px-2 py-0.5 text-xs font-semibold bg-status-warning/10 text-status-warning border border-status-warning/40 rounded-full">
                        VIP
                    </span>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-text-muted hover:text-text-primary transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Form Body -->
            <form @submit.prevent="submit" class="p-6 flex flex-col gap-5">
                <!-- 1. Session Type Selector (Prepaid / Postpaid) -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                        Session Billing Model
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="form.session_type = 'prepaid'"
                            :class="[
                                'py-3 px-4 rounded-lg border text-sm font-semibold flex items-center justify-center gap-2 transition cursor-pointer',
                                form.session_type === 'prepaid'
                                    ? 'bg-brand-primary text-text-primary border-transparent shadow-md'
                                    : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                            ]"
                        >
                            <Clock class="w-4 h-4" /> Prepaid Countdown
                        </button>
                        <button
                            type="button"
                            @click="form.session_type = 'postpaid'"
                            :class="[
                                'py-3 px-4 rounded-lg border text-sm font-semibold flex items-center justify-center gap-2 transition cursor-pointer',
                                form.session_type === 'postpaid'
                                    ? 'bg-brand-primary text-text-primary border-transparent shadow-md'
                                    : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                            ]"
                        >
                            <Zap class="w-4 h-4" /> Open Postpaid Tab
                        </button>
                    </div>
                </div>

                <!-- 2. Controller Player Count Tier -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                        Select Active Controllers
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="tier in pricingTiers"
                            :key="tier.id"
                            type="button"
                            @click="form.pricing_tier_id = tier.id"
                            :class="[
                                'p-3 rounded-lg border text-left flex flex-col justify-between transition cursor-pointer',
                                form.pricing_tier_id === tier.id
                                    ? 'bg-surface-elevated border-brand-primary'
                                    : 'bg-surface-canvas border-surface-border-subtle hover:border-surface-border'
                            ]"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-semibold text-text-primary flex items-center gap-1.5">
                                    <Users class="w-3.5 h-3.5 text-text-muted" />
                                    {{ tier.name }}
                                </span>
                            </div>
                            <div class="text-sm font-semibold text-text-primary font-mono tabular-nums">
                                {{ getTierRateLyd(tier) }} LYD/hr
                            </div>
                        </button>
                    </div>
                </div>

                <!-- 3. Prepaid Duration Presets (if Prepaid) -->
                <div v-if="form.session_type === 'prepaid'">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                        Allocated Play Time
                    </label>
                    <div class="grid grid-cols-5 gap-2 mb-3">
                        <button
                            v-for="preset in durationPresets"
                            :key="preset.minutes"
                            type="button"
                            @click="form.allocated_minutes = preset.minutes"
                            :class="[
                                'py-2 px-1 text-xs font-semibold rounded-lg border text-center transition cursor-pointer',
                                form.allocated_minutes === preset.minutes
                                    ? 'bg-brand-primary text-text-primary border-transparent'
                                    : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                            ]"
                        >
                            {{ preset.label }}
                        </button>
                    </div>

                    <!-- Custom Duration Input -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex-1">
                            <input
                                v-model.number="form.allocated_minutes"
                                type="number"
                                min="5"
                                step="5"
                                class="w-full px-3 py-2 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-sm focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                placeholder="Custom minutes"
                            />
                        </div>
                        <div class="text-xs text-text-muted font-medium">
                            Upfront Cost: <span class="font-semibold text-text-primary font-mono">{{ Math.round(estimatedPrepaidCostLyd) }} LYD</span>
                        </div>
                    </div>

                    <!-- Payment Timing Selector (Pay Before Starts vs Pay After Ends) -->
                    <div class="flex flex-col gap-2.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">
                            Prepaid Payment Timing
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="form.prepaid_payment_timing = 'before'"
                                :class="[
                                    'py-2.5 px-3 rounded-lg border text-xs font-semibold flex items-center justify-center gap-1.5 transition cursor-pointer',
                                    form.prepaid_payment_timing === 'before'
                                        ? 'bg-brand-primary text-text-primary border-transparent shadow-sm'
                                        : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border-surface-border-subtle'
                                ]"
                            >
                                <CheckCircle2 class="w-3.5 h-3.5" /> Pay Before Session Starts
                            </button>
                            <button
                                type="button"
                                @click="form.prepaid_payment_timing = 'after'"
                                :class="[
                                    'py-2.5 px-3 rounded-lg border text-xs font-semibold flex items-center justify-center gap-1.5 transition cursor-pointer',
                                    form.prepaid_payment_timing === 'after'
                                        ? 'bg-brand-primary text-text-primary border-transparent shadow-sm'
                                        : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border-surface-border-subtle'
                                ]"
                            >
                                <Clock class="w-3.5 h-3.5" /> Pay After Session Ends
                            </button>
                        </div>

                        <!-- Upfront Cash Collection UI (Only if paying before) -->
                        <div v-if="form.prepaid_payment_timing === 'before'" class="p-3.5 rounded-xl bg-surface-overlay border border-surface-border-subtle flex flex-col gap-2.5 mt-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-text-muted flex items-center gap-1.5">
                                    <Banknote class="w-3.5 h-3.5" /> Cash Received (LYD)
                                </span>
                                <span class="text-xs font-mono text-text-secondary">
                                    Due: <strong class="text-text-primary">{{ estimatedPrepaidCostLyd }} LYD</strong>
                                </span>
                            </div>

                            <!-- 3 Options: 5, 10, 20 -->
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="preset in cashPresets"
                                    :key="preset"
                                    type="button"
                                    @click="form.cash_received_lyd = preset"
                                    :class="[
                                        'py-1.5 text-xs font-mono font-semibold rounded-lg border text-center transition cursor-pointer',
                                        form.cash_received_lyd === preset
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
                                        v-model.number="form.cash_received_lyd"
                                        type="number"
                                        step="5"
                                        min="0"
                                        class="w-full px-3 py-1.5 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary font-mono text-xs focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                                        placeholder="Paid amount"
                                    />
                                </div>
                                <div class="text-right">
                                    <span class="text-[11px] text-text-muted block">Change to Return:</span>
                                    <span :class="['text-sm font-mono font-semibold tabular-nums', changeDueLyd >= 0 ? 'text-status-available' : 'text-status-rogue']">
                                        {{ Math.round(changeDueLyd) }} LYD
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-2.5 rounded-lg bg-surface-canvas border border-surface-border-subtle text-xs text-text-muted">
                            Session will be tagged as <span class="text-status-warning font-semibold">Unpaid</span> until payment is collected at checkout.
                        </div>
                    </div>
                </div>

                <!-- 4. Optional Backdate / Rogue Claim -->
                <div v-if="initialBackdateMinutes && initialBackdateMinutes > 0" class="p-3 rounded-xl bg-status-warning/10 border border-status-warning/30 text-xs text-status-warning">
                    <p class="font-semibold flex items-center gap-1.5 mb-1">
                        <ShieldCheck class="w-4 h-4" /> Backdating start time by {{ initialBackdateMinutes }} min(s)
                    </p>
                    <p class="text-text-secondary">Session timer will include the unauthorized play duration detected earlier.</p>
                </div>

                <!-- 5. Customer Name (Optional) -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-1">
                        Customer Name / Tag (Optional)
                    </label>
                    <div class="relative">
                        <User class="w-4 h-4 text-text-muted absolute left-3 top-2.5" />
                        <input
                            v-model="form.customer_name"
                            type="text"
                            placeholder="e.g. Ahmed or Squad A"
                            class="w-full pl-9 pr-3 py-2 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary text-sm focus:outline-none focus:border-brand-primary placeholder:text-text-muted"
                        />
                    </div>
                </div>

                <!-- 6. Toggle Auto Wake TV Screen -->
                <div v-if="tvControlEnabled" class="flex items-center justify-between p-3 rounded-xl bg-surface-canvas border border-surface-border-subtle text-xs">
                    <span class="text-text-secondary font-medium">Turn On TV Screen (Wake-on-LAN):</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="form.auto_wake" class="sr-only peer" />
                        <div class="w-9 h-5 bg-surface-border peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-text-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-text-primary after:border-surface-border after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-primary"></div>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3.5 px-4 rounded-lg bg-brand-primary hover:bg-brand-primary-hover disabled:opacity-50 text-text-primary font-semibold text-sm transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <Play class="w-4 h-4 fill-current" />
                        {{ form.processing ? 'Starting...' : 'Start Gaming Session' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
