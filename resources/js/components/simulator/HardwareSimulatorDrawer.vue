<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Tv, Power, MonitorSmartphone, Moon, AlertTriangle, FastForward,
    RefreshCw, X, ShieldAlert, Zap
} from 'lucide-vue-next';
import type { StationData } from '../StationCard.vue';

const props = defineProps<{
    show: boolean;
    stations: StationData[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

function simulateWake(station: StationData) {
    router.post(`/simulator/stations/${station.id}/wake`, {}, { preserveScroll: true });
}

function simulateSleep(station: StationData) {
    router.post(`/simulator/stations/${station.id}/sleep`, {}, { preserveScroll: true });
}

function triggerRogue(station: StationData) {
    router.post(`/simulator/stations/${station.id}/rogue`, {}, { preserveScroll: true });
}

function fastForward(station: StationData, minutes: number = 15) {
    router.post(`/simulator/stations/${station.id}/fast-forward`, { minutes }, { preserveScroll: true });
}

function runReconciliation() {
    router.post('/reconciliation/sync', {}, { preserveScroll: true });
}
</script>

<template>
    <div
        v-if="show"
        class="fixed bottom-0 inset-x-0 z-40 bg-surface-elevated border-t border-surface-border shadow-xl backdrop-blur-lg max-h-[45vh] flex flex-col transition-all duration-300"
    >
        <!-- Header -->
        <div class="px-6 py-2.5 border-b border-surface-border-subtle flex items-center justify-between">
            <div class="flex items-center gap-2">
                <MonitorSmartphone class="w-4 h-4 text-text-muted" />
                <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Dev Simulator</h3>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="runReconciliation"
                    type="button"
                    class="px-3 py-1 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle flex items-center gap-1.5 transition cursor-pointer"
                >
                    <RefreshCw class="w-3.5 h-3.5" /> Run TV Reconciliation
                </button>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-text-muted hover:text-text-primary transition cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Simulator Stations Grid -->
        <div class="p-4 overflow-y-auto flex-1">
            <div class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                <div
                    v-for="st in stations"
                    :key="st.id"
                    class="p-3 rounded-lg bg-surface-overlay border border-surface-border-subtle flex flex-col justify-between gap-2.5"
                >
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-text-primary">{{ st.name }}</span>
                        <div class="flex items-center gap-1">
                            <span
                                :class="[
                                    'w-2 h-2 rounded-full',
                                    st.tv_physical_state === 'screen_on' ? 'bg-status-available' : 'bg-text-muted'
                                ]"
                            ></span>
                            <span class="text-xs font-mono text-text-secondary uppercase">
                                {{ st.tv_physical_state === 'screen_on' ? 'SCREEN ON' : 'STANDBY' }}
                            </span>
                            <span class="text-xs uppercase font-semibold text-text-muted">
                                {{ st.tv_os_type }}
                            </span>
                        </div>
                    </div>

                    <!-- Trigger Buttons -->
                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            @click="simulateWake(st)"
                            type="button"
                            class="py-1.5 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                            title="Simulate TV Screen ON (WoL)"
                        >
                            <Zap class="w-3.5 h-3.5" /> TV ON (WoL)
                        </button>
                        <button
                            @click="simulateSleep(st)"
                            type="button"
                            class="py-1.5 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                            title="Simulate TV Screen Standby (OFF)"
                        >
                            <Moon class="w-3.5 h-3.5" /> TV OFF
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            @click="triggerRogue(st)"
                            type="button"
                            class="py-1 px-1.5 rounded-lg bg-surface-elevated hover:bg-surface-border text-status-rogue text-xs font-semibold border border-status-rogue/40 transition flex items-center justify-center gap-1 cursor-pointer truncate"
                            title="Simulate Customer Turning On TV via Physical Remote"
                        >
                            <ShieldAlert class="w-3.5 h-3.5" /> Remote Turn-On ({{ st.consecutive_on_pings }})
                        </button>
                        <button
                            v-if="st.active_session"
                            @click="fastForward(st, 15)"
                            type="button"
                            class="py-1 px-1.5 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                            title="Fast-forward session clock by 15 mins"
                        >
                            <FastForward class="w-3.5 h-3.5" /> +15m
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
