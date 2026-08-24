<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Play, Clock, Zap, Users, User, ShieldCheck } from 'lucide-vue-next';
import type { StationData } from '../StationCard.vue';

const props = defineProps<{
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
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const form = useForm({
    station_id: 0,
    session_type: 'prepaid' as 'prepaid' | 'postpaid',
    pricing_tier_id: 0,
    allocated_minutes: 60,
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

function initialize() {
    if (!props.station) return;
    form.station_id = props.station.id;
    form.pricing_tier_id = props.pricingTiers[0]?.id ?? 1;
    form.session_type = 'prepaid';
    form.allocated_minutes = 60;
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

const estimatedPrepaidCostLyd = computed(() => {
    if (form.session_type !== 'prepaid' || !selectedTier.value || !form.allocated_minutes) {
        return 0;
    }
    const mult = props.station?.is_vip ? 1.50 : 1.00;
    return (form.allocated_minutes / 60) * selectedTier.value.hourly_rate_lyd * mult;
});

function submit() {
    form.post('/sessions/start', {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}
</script>

<template>
    <div v-if="show && station" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-lg bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-[#1e293b]/70 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-sky-400"></span>
                    <h3 class="text-lg font-bold text-white">
                        Start Session — {{ station.name }}
                    </h3>
                    <span v-if="station.is_vip" class="px-2 py-0.5 text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 rounded">
                        VIP
                    </span>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Form Body -->
            <form @submit.prevent="submit" class="p-6 flex flex-col gap-5">
                <!-- 1. Session Type Selector (Prepaid / Postpaid) -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Session Billing Model
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="form.session_type = 'prepaid'"
                            :class="[
                                'py-3 px-4 rounded-xl border text-sm font-bold flex items-center justify-center gap-2 transition cursor-pointer',
                                form.session_type === 'prepaid'
                                    ? 'bg-sky-600/20 border-sky-500 text-sky-300 shadow-md shadow-sky-500/10'
                                    : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:border-slate-700'
                            ]"
                        >
                            <Clock class="w-4 h-4" /> Prepaid Countdown
                        </button>
                        <button
                            type="button"
                            @click="form.session_type = 'postpaid'"
                            :class="[
                                'py-3 px-4 rounded-xl border text-sm font-bold flex items-center justify-center gap-2 transition cursor-pointer',
                                form.session_type === 'postpaid'
                                    ? 'bg-purple-600/20 border-purple-500 text-purple-300 shadow-md shadow-purple-500/10'
                                    : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:border-slate-700'
                            ]"
                        >
                            <Zap class="w-4 h-4" /> Open Postpaid Tab
                        </button>
                    </div>
                </div>

                <!-- 2. Controller Player Count Tier -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Select Active Controllers
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="tier in pricingTiers"
                            :key="tier.id"
                            type="button"
                            @click="form.pricing_tier_id = tier.id"
                            :class="[
                                'p-3 rounded-xl border text-left flex flex-col justify-between transition cursor-pointer',
                                form.pricing_tier_id === tier.id
                                    ? 'bg-slate-800 border-sky-500 ring-1 ring-sky-500'
                                    : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'
                            ]"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <Users class="w-3.5 h-3.5 text-sky-400" />
                                    {{ tier.name }}
                                </span>
                            </div>
                            <div class="text-sm font-bold text-sky-400 font-mono tabular-nums">
                                {{ (tier.hourly_rate_lyd * (station.is_vip ? 1.50 : 1.00)).toFixed(3) }} LYD/hr
                            </div>
                        </button>
                    </div>
                </div>

                <!-- 3. Prepaid Duration Presets (if Prepaid) -->
                <div v-if="form.session_type === 'prepaid'">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Allocated Play Time
                    </label>
                    <div class="grid grid-cols-5 gap-2 mb-3">
                        <button
                            v-for="preset in durationPresets"
                            :key="preset.minutes"
                            type="button"
                            @click="form.allocated_minutes = preset.minutes"
                            :class="[
                                'py-2 px-1 text-xs font-bold rounded-lg border text-center transition cursor-pointer',
                                form.allocated_minutes === preset.minutes
                                    ? 'bg-sky-500 text-slate-950 border-sky-400'
                                    : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-slate-700'
                            ]"
                        >
                            {{ preset.label }}
                        </button>
                    </div>

                    <!-- Custom Duration Input -->
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <input
                                v-model.number="form.allocated_minutes"
                                type="number"
                                min="5"
                                step="5"
                                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-sky-500"
                                placeholder="Custom minutes"
                            />
                        </div>
                        <div class="text-xs text-slate-400 font-medium">
                            Upfront Cost: <span class="font-bold text-sky-400 font-mono">{{ estimatedPrepaidCostLyd.toFixed(3) }} LYD</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Optional Backdate / Rogue Claim -->
                <div v-if="initialBackdateMinutes && initialBackdateMinutes > 0" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-200">
                    <p class="font-bold flex items-center gap-1.5 mb-1">
                        <ShieldCheck class="w-4 h-4 text-amber-400" /> Backdating start time by {{ initialBackdateMinutes }} min(s)
                    </p>
                    <p class="text-slate-300">Session timer will include the unauthorized play duration detected earlier.</p>
                </div>

                <!-- 5. Customer Name (Optional) -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                        Customer Name / Tag (Optional)
                    </label>
                    <div class="relative">
                        <User class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" />
                        <input
                            v-model="form.customer_name"
                            type="text"
                            placeholder="e.g. Ahmed or Squad A"
                            class="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-sky-500 placeholder-slate-600"
                        />
                    </div>
                </div>

                <!-- 6. Toggle Auto Wake TV Screen -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-xs">
                    <span class="text-slate-300 font-medium">Turn On TV Screen (Wake-on-LAN):</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="form.auto_wake" class="sr-only peer" />
                        <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-600"></div>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3.5 px-4 rounded-xl bg-sky-600 hover:bg-sky-500 disabled:opacity-50 text-white font-bold text-sm shadow-lg shadow-sky-600/30 transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <Play class="w-4 h-4 fill-current" />
                        {{ form.processing ? 'Starting...' : 'Start Gaming Session' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
