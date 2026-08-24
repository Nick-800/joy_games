<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { 
    Tv, Power, Moon, AlertTriangle, FastForward, 
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
        class="fixed bottom-0 inset-x-0 z-40 bg-[#090d16]/98 border-t-2 border-amber-500/50 shadow-2xl backdrop-blur-lg max-h-[45vh] flex flex-col transition-all duration-300"
    >
        <!-- Header -->
        <div class="px-6 py-2.5 bg-[#0f172a] border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded bg-amber-500/20 text-amber-400 flex items-center justify-center font-mono font-bold text-xs">
                    📺
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        Smart TV IP Control Simulator & Dev Toolbar
                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-400/20 text-amber-300 font-normal">
                            Pure IP Testing Mode
                        </span>
                    </h3>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="runReconciliation"
                    type="button"
                    class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 flex items-center gap-1.5 transition cursor-pointer"
                >
                    <RefreshCw class="w-3.5 h-3.5 text-sky-400" /> Run TV Reconciliation
                </button>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-slate-400 hover:text-white transition cursor-pointer"
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
                    class="p-3 rounded-xl bg-[#0f172a] border border-slate-800 flex flex-col justify-between gap-2.5"
                >
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-white">{{ st.name }}</span>
                        <div class="flex items-center gap-1">
                            <span
                                :class="[
                                    'w-2 h-2 rounded-full',
                                    st.tv_physical_state === 'screen_on' ? 'bg-emerald-400' : 'bg-slate-500'
                                ]"
                            ></span>
                            <span class="text-[10px] font-mono text-slate-400 uppercase">
                                {{ st.tv_physical_state === 'screen_on' ? 'SCREEN ON' : 'STANDBY' }}
                            </span>
                            <span class="text-[9px] uppercase font-bold text-amber-400/80">
                                {{ st.tv_os_type }}
                            </span>
                        </div>
                    </div>

                    <!-- Trigger Buttons -->
                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            @click="simulateWake(st)"
                            type="button"
                            class="py-1.5 px-2 rounded-lg bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white text-[11px] font-bold border border-emerald-500/30 transition flex items-center justify-center gap-1 cursor-pointer"
                            title="Simulate TV Screen ON (WoL)"
                        >
                            <Zap class="w-3 h-3" /> TV ON (WoL)
                        </button>
                        <button
                            @click="simulateSleep(st)"
                            type="button"
                            class="py-1.5 px-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-bold border border-slate-700 transition flex items-center justify-center gap-1 cursor-pointer"
                            title="Simulate TV Screen Standby (OFF)"
                        >
                            <Moon class="w-3 h-3" /> TV OFF
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            @click="triggerRogue(st)"
                            type="button"
                            class="py-1 px-1.5 rounded-lg bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white text-[10px] font-bold border border-rose-500/30 transition flex items-center justify-center gap-1 cursor-pointer truncate"
                            title="Simulate Customer Turning On TV via Physical Remote"
                        >
                            <ShieldAlert class="w-3 h-3" /> Remote Turn-On ({{ st.consecutive_on_pings }})
                        </button>
                        <button
                            v-if="st.active_session"
                            @click="fastForward(st, 15)"
                            type="button"
                            class="py-1 px-1.5 rounded-lg bg-sky-600/20 hover:bg-sky-600 text-sky-300 hover:text-white text-[10px] font-bold border border-sky-500/30 transition flex items-center justify-center gap-1 cursor-pointer"
                            title="Fast-forward session clock by 15 mins"
                        >
                            <FastForward class="w-3 h-3" /> +15m
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
