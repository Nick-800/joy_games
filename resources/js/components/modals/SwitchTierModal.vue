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
        <div class="w-full max-w-md bg-surface-elevated border border-surface-border rounded-2xl shadow-xl overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-surface-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Users class="w-5 h-5 text-text-muted" />
                    <h3 class="text-base font-semibold text-text-primary">
                        Switch Player Tier — {{ station.name }}
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
            <div class="p-6 flex flex-col gap-4">
                <div class="p-3.5 rounded-xl bg-brand-primary/10 border border-brand-primary/30 text-xs text-text-secondary">
                    <p class="font-semibold flex items-center gap-1.5 mb-1">
                        <ShieldCheck class="w-4 h-4" /> Sliced Interval Billing Engine
                    </p>
                    <p class="text-text-secondary">
                        Switching tiers will seamlessly finalize the elapsed time at the current rate and start a new billing slice at the new rate from this moment forward.
                    </p>
                </div>

                <div class="space-y-2.5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-text-muted">
                        Choose New Active Controller Count:
                    </h4>
                    <button
                        v-for="tier in pricingTiers"
                        :key="tier.id"
                        @click="switchTier(tier.id)"
                        type="button"
                        :class="[
                            'w-full p-4 rounded-lg border text-left flex items-center justify-between transition cursor-pointer',
                            station.active_session?.current_tier?.id === tier.id
                                ? 'bg-surface-elevated border-brand-primary'
                                : 'bg-surface-canvas border-surface-border-subtle hover:border-surface-border hover:bg-surface-elevated'
                        ]"
                    >
                        <div>
                            <span class="text-sm font-semibold text-text-primary block">{{ tier.name }}</span>
                            <span class="text-xs text-text-secondary">{{ tier.controller_count_min }}–{{ tier.controller_count_max }} Controllers Active</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-text-primary font-mono tabular-nums block">
                                {{ (tier.hourly_rate_lyd * (station.is_vip ? 1.50 : 1.00)).toFixed(3) }} LYD/hr
                            </span>
                            <span v-if="station.active_session?.current_tier?.id === tier.id" class="text-xs font-semibold text-status-available uppercase">
                                Currently Active
                            </span>
                            <span v-else class="text-xs font-semibold text-text-secondary uppercase flex items-center gap-1 justify-end">
                                Switch <ArrowRight class="w-3 h-3" />
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
