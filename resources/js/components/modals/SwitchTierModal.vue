<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X, Users, ArrowRight, ShieldCheck } from 'lucide-vue-next';
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
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

function switchTier(tierId: number) {
    if (!props.station?.active_session) return;

    const form = useForm({
        pricing_tier_id: tierId,
    });

    form.post(`/sessions/${props.station.active_session.id}/switch-tier`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}
</script>

<template>
    <div v-if="show && station" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-md bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 bg-[#1e293b]/70 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Users class="w-5 h-5 text-sky-400" />
                    <h3 class="text-lg font-bold text-white">
                        Switch Player Tier — {{ station.name }}
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
            <div class="p-6 flex flex-col gap-4">
                <div class="p-3.5 rounded-xl bg-sky-500/10 border border-sky-500/30 text-xs text-sky-200">
                    <p class="font-bold flex items-center gap-1.5 mb-1">
                        <ShieldCheck class="w-4 h-4 text-sky-400" /> Sliced Interval Billing Engine
                    </p>
                    <p class="text-slate-300">
                        Switching tiers will seamlessly finalize the elapsed time at the current rate and start a new billing slice at the new rate from this moment forward.
                    </p>
                </div>

                <div class="space-y-2.5">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Choose New Active Controller Count:
                    </h4>
                    <button
                        v-for="tier in pricingTiers"
                        :key="tier.id"
                        @click="switchTier(tier.id)"
                        type="button"
                        :class="[
                            'w-full p-4 rounded-xl border text-left flex items-center justify-between transition cursor-pointer',
                            station.active_session?.current_tier?.id === tier.id
                                ? 'bg-slate-800 border-sky-500 ring-1 ring-sky-500'
                                : 'bg-[#090d16] border-slate-800 hover:border-sky-500/60 hover:bg-slate-900'
                        ]"
                    >
                        <div>
                            <span class="text-sm font-bold text-white block">{{ tier.name }}</span>
                            <span class="text-xs text-slate-400">{{ tier.controller_count_min }}–{{ tier.controller_count_max }} Controllers Active</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold text-sky-400 font-mono block">
                                {{ (tier.hourly_rate_lyd * (station.is_vip ? 1.50 : 1.00)).toFixed(3) }} LYD/hr
                            </span>
                            <span v-if="station.active_session?.current_tier?.id === tier.id" class="text-[10px] font-bold text-emerald-400 uppercase">
                                Currently Active
                            </span>
                            <span v-else class="text-[10px] font-bold text-sky-300 uppercase flex items-center gap-1 justify-end">
                                Switch <ArrowRight class="w-3 h-3" />
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
