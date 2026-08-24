<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Gamepad2, User, Wallet, Clock, Activity, Cpu, KeyRound } from 'lucide-vue-next';

const props = defineProps<{
    activeShift: {
        shift_id: number;
        cashier_name: string;
        opening_float_lyd: number;
        expected_current_cash_lyd: number;
        completed_sessions_count: number;
    } | null;
    activeStationsCount: number;
    totalStationsCount: number;
    simulatorOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggleSimulator'): void;
    (e: 'openShiftModal'): void;
    (e: 'openPinModal'): void;
}>();

const currentTime = ref('');

function updateTime() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-GB', { hour12: false });
}

let timer: number;
onMounted(() => {
    updateTime();
    timer = window.setInterval(updateTime, 1000);
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>

<template>
    <header class="sticky top-0 z-40 bg-[#0f172a]/95 backdrop-blur-md border-b border-[#1e293b] px-4 lg:px-6 py-3 shadow-xl">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
            <!-- Brand & Lounge Logo -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-sky-500/20 ring-1 ring-white/20">
                    <Gamepad2 class="w-6 h-6 text-white" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold tracking-tight text-white flex items-center gap-2">
                            JOY GAMES
                            <span class="text-xs px-2 py-0.5 rounded-full bg-sky-500/20 text-sky-400 font-semibold border border-sky-500/30">
                                PS5 LOUNGE
                            </span>
                        </h1>
                    </div>
                    <p class="text-xs text-slate-400 font-medium">Local-First Hardware Automation & Sliced Billing</p>
                </div>
            </div>

            <!-- Central Operational Badges -->
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <!-- Clock -->
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-slate-300 text-xs font-mono tabular-nums shadow-inner">
                    <Clock class="w-3.5 h-3.5 text-sky-400" />
                    <span>{{ currentTime }}</span>
                </div>

                <!-- Active Stations KPI -->
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-xs font-medium text-slate-300">
                    <Activity class="w-3.5 h-3.5 text-emerald-400" />
                    <span>Stations:</span>
                    <span class="font-bold text-white tabular-nums">{{ activeStationsCount }} / {{ totalStationsCount }}</span>
                </div>

                <!-- Active Shift Drawer Float -->
                <button
                    @click="emit('openShiftModal')"
                    type="button"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#090d16] hover:bg-[#1e293b] border border-[#1e293b] hover:border-slate-600 transition text-xs text-slate-300 cursor-pointer group"
                >
                    <Wallet class="w-3.5 h-3.5 text-amber-400 group-hover:scale-110 transition" />
                    <span class="text-slate-400">Shift Float:</span>
                    <span v-if="activeShift" class="font-bold text-amber-300 tabular-nums">
                        {{ activeShift.opening_float_lyd.toFixed(3) }} LYD
                    </span>
                    <span v-else class="text-rose-400 font-semibold">No Open Shift</span>
                </button>

                <!-- Active Staff Switcher -->
                <button
                    @click="emit('openPinModal')"
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 text-sky-300 hover:text-white transition text-xs font-medium cursor-pointer"
                >
                    <User class="w-3.5 h-3.5 text-sky-400" />
                    <span>{{ activeShift?.cashier_name ?? 'Staff Switch' }}</span>
                    <KeyRound class="w-3 h-3 text-sky-400/70 ml-1" />
                </button>
            </div>

            <!-- Right Controls: Dev Simulator Toggle -->
            <div class="flex items-center gap-2">
                <button
                    @click="emit('toggleSimulator')"
                    type="button"
                    :class="[
                        'flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition shadow-sm cursor-pointer',
                        simulatorOpen
                            ? 'bg-amber-500 text-slate-950 ring-2 ring-amber-400/50'
                            : 'bg-[#1e293b] hover:bg-[#334155] text-slate-200 border border-slate-700'
                    ]"
                >
                    <Cpu class="w-4 h-4" />
                    <span>⚙️ Dev Simulator</span>
                    <span
                        v-if="simulatorOpen"
                        class="w-2 h-2 rounded-full bg-slate-950 animate-ping"
                    ></span>
                </button>
            </div>
        </div>
    </header>
</template>
