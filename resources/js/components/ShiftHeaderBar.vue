<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3';
import { Gamepad2, User, Wallet, Clock, Activity, Cpu, KeyRound, LogOut, Receipt } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';

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
    currentUser?: { id: number; name: string; email: string; role: string } | null;
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
    <header class="sticky top-0 z-40 bg-surface-elevated/95 backdrop-blur-md border-b border-surface-border-subtle px-4 lg:px-6 py-3 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
            <!-- Brand & Lounge Logo -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-brand-primary flex items-center justify-center">
                    <Gamepad2 class="w-6 h-6" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-semibold tracking-tight text-text-primary flex items-center gap-2">
                            JOY GAMES
                            <span class="text-xs px-2 py-0.5 rounded-full bg-surface-overlay text-text-muted font-semibold border border-surface-border-subtle">
                                PS5 LOUNGE
                            </span>
                        </h1>
                    </div>
                </div>
            </div>

            <!-- Central Operational Badges -->
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <!-- Clock -->
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-overlay border border-surface-border-subtle text-text-secondary text-xs font-mono tabular-nums">
                    <Clock class="w-3.5 h-3.5 text-text-muted" />
                    <span>{{ currentTime }}</span>
                </div>

                <!-- Active Stations KPI -->
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-overlay border border-surface-border-subtle text-xs font-medium text-text-secondary">
                    <Activity class="w-3.5 h-3.5 text-text-muted" />
                    <span>Stations:</span>
                    <span class="font-semibold text-text-primary tabular-nums">{{ activeStationsCount }} / {{ totalStationsCount }}</span>
                </div>

                <!-- Active Shift Drawer Float -->
                <button
                    @click="emit('openShiftModal')"
                    type="button"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-overlay hover:bg-surface-border border border-surface-border-subtle transition text-xs text-text-secondary cursor-pointer"
                >
                    <Wallet class="w-3.5 h-3.5 text-text-muted" />
                    <span class="text-text-muted">Shift Float:</span>
                    <span v-if="activeShift" class="font-semibold text-text-primary tabular-nums">
                        {{ activeShift.opening_float_lyd.toFixed(3) }}
                    </span>
                    <span v-else class="text-status-rogue font-semibold">No Open Shift</span>
                </button>

                <!-- Active Staff Switcher -->
                <button
                    @click="emit('openPinModal')"
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle transition text-xs font-medium cursor-pointer"
                >
                    <User class="w-3.5 h-3.5 text-text-muted" />
                    <span>{{ activeShift?.cashier_name ?? 'Staff Switch' }}</span>
                    <KeyRound class="w-3 h-3 text-text-muted ml-1" />
                </button>
            </div>

            <!-- Right Controls: Dev Simulator Toggle -->
            <div class="flex items-center gap-2">
                <Link
                    href="/reports"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle transition cursor-pointer"
                    title="View revenue reports"
                >
                    <Receipt class="w-3.5 h-3.5" />
                    <span>Reports</span>
                </Link>
                <button
                    @click="emit('toggleSimulator')"
                    type="button"
                    :class="[
                        'flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition shadow-sm cursor-pointer',
                        simulatorOpen
                            ? 'bg-brand-primary text-text-primary'
                            : 'bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle'
                    ]"
                >
                    <Cpu class="w-4 h-4" />
                    <span>Dev Simulator</span>
                </button>
                <button
                    v-if="currentUser"
                    @click="router.post('/logout')"
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-surface-elevated hover:bg-status-rogue hover:text-text-primary text-text-secondary border border-surface-border-subtle transition cursor-pointer"
                    title="Sign out"
                >
                    <LogOut class="w-3.5 h-3.5" />
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </header>
</template>
