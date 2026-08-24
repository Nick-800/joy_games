<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { 
    Tv, Clock, Zap, AlertTriangle, Play, Plus, 
    Pause, ShieldAlert, ArrowRightLeft, 
    CheckCircle2, DollarSign, Users, Moon,
    Sparkles, Coffee, Power
} from 'lucide-vue-next';

interface PricingTierInfo {
    id: number;
    name: string;
    rate_per_hour_millimes: number;
    rate_per_hour_lyd: number;
}

interface OrderItemInfo {
    id: number;
    item_name: string;
    quantity: number;
    subtotal_lyd: number;
}

interface IntervalInfo {
    id: number;
    pricing_tier: string;
    rate_per_hour_millimes: number;
    station_multiplier: number;
    started_at: string;
    ended_at?: string | null;
    duration_seconds: number;
    billable_minutes: number;
    subtotal_millimes: number;
    is_ongoing: boolean;
}

interface ActiveSessionInfo {
    id: number;
    session_type: 'prepaid' | 'postpaid';
    status: string;
    customer_name?: string | null;
    allocated_minutes?: number | null;
    started_at: string;
    paused_at?: string | null;
    total_paused_seconds: number;
    pause_reason?: string | null;
    current_tier?: PricingTierInfo | null;
    time_amount_lyd: number;
    retail_amount_lyd: number;
    final_total_lyd: number;
    intervals: IntervalInfo[];
    order_items: OrderItemInfo[];
}

export interface StationData {
    id: number;
    name: string;
    station_number: number;
    type: 'standard' | 'vip';
    is_vip: boolean;
    tv_ip_address?: string | null;
    tv_mac_address?: string | null;
    tv_os_type: string;
    tv_physical_state: 'standby' | 'screen_on' | 'unreachable';
    current_state: 'available' | 'active_prepaid' | 'active_postpaid' | 'paused' | 'payment_pending' | 'maintenance';
    consecutive_on_pings: number;
    is_rogue: boolean;
    rogue_duration_seconds: number;
    first_detected_on_at?: string | null;
    active_session?: ActiveSessionInfo | null;
}

const props = defineProps<{
    station: StationData;
}>();

const emit = defineEmits<{
    (e: 'start-session', station: StationData): void;
    (e: 'add-retail-item', station: StationData): void;
    (e: 'switch-tier', station: StationData): void;
    (e: 'extend-time', station: StationData, minutes: number): void;
    (e: 'pause-session', station: StationData): void;
    (e: 'resume-session', station: StationData): void;
    (e: 'end-session', station: StationData): void;
    (e: 'settle-payment', station: StationData): void;
    (e: 'transfer-station', station: StationData): void;
    (e: 'force-sleep', station: StationData): void;
    (e: 'claim-rogue', station: StationData): void;
}>();

// Client-side real-time clock ticker
const now = ref(Date.now());
let timerInterval: number | null = null;

onMounted(() => {
    timerInterval = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// Computed time elapsed / remaining
const elapsedSeconds = computed(() => {
    if (!props.station.active_session?.started_at) return 0;
    const startMs = new Date(props.station.active_session.started_at).getTime();
    const pausedSeconds = props.station.active_session.total_paused_seconds || 0;
    
    // If currently paused, calculate up to paused_at
    if (props.station.active_session.paused_at) {
        const pauseMs = new Date(props.station.active_session.paused_at).getTime();
        return Math.max(0, Math.floor((pauseMs - startMs) / 1000) - pausedSeconds);
    }

    return Math.max(0, Math.floor((now.value - startMs) / 1000) - pausedSeconds);
});

const remainingSeconds = computed(() => {
    if (props.station.active_session?.session_type !== 'prepaid') return 0;
    const allocated = (props.station.active_session.allocated_minutes || 0) * 60;
    return Math.max(0, allocated - elapsedSeconds.value);
});

const isExpiringSoon = computed(() => {
    return props.station.current_state === 'active_prepaid' 
        && remainingSeconds.value <= 300 
        && remainingSeconds.value > 0;
});

// Formatted time display
const timerDisplay = computed(() => {
    if (props.station.is_rogue) {
        const rogueSecs = props.station.rogue_duration_seconds || 0;
        return formatHms(rogueSecs);
    }

    if (props.station.current_state === 'active_prepaid') {
        return formatHms(remainingSeconds.value);
    }

    if (['active_postpaid', 'paused', 'payment_pending'].includes(props.station.current_state)) {
        return formatHms(elapsedSeconds.value);
    }

    return '00:00:00';
});

function formatHms(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return [
        h.toString().padStart(2, '0'),
        m.toString().padStart(2, '0'),
        s.toString().padStart(2, '0')
    ].join(':');
}

// Status styling & badges
const cardTheme = computed(() => {
    if (props.station.is_rogue) {
        return 'border-rose-500/80 bg-rose-950/20 shadow-lg shadow-rose-950/40 ring-1 ring-rose-500/50 animate-pulse';
    }

    switch (props.station.current_state) {
        case 'available':
            return 'border-emerald-500/30 bg-[#0f172a]/90 hover:border-emerald-500/60 shadow-md';
        case 'active_prepaid':
            if (isExpiringSoon.value) {
                return 'border-amber-500/80 bg-amber-950/20 ring-1 ring-amber-500/50 shadow-lg shadow-amber-950/30';
            }
            return 'border-sky-500/50 bg-[#0f172a] ring-1 ring-sky-500/30 shadow-lg shadow-sky-950/30';
        case 'active_postpaid':
            return 'border-purple-500/50 bg-[#0f172a] ring-1 ring-purple-500/30 shadow-lg shadow-purple-950/30';
        case 'paused':
            return 'border-amber-500/40 bg-amber-950/10 shadow-md';
        case 'payment_pending':
            return 'border-amber-500/70 bg-amber-950/20 shadow-lg shadow-amber-950/40';
        default:
            return 'border-slate-800 bg-slate-900/50';
    }
});

const statusBadge = computed(() => {
    if (props.station.is_rogue) {
        return { text: 'UNAUTHORIZED TV ON', bg: 'bg-rose-500/20 text-rose-300 border-rose-500/40 animate-pulse' };
    }

    switch (props.station.current_state) {
        case 'available':
            return { text: 'TV OFF (Ready)', bg: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' };
        case 'active_prepaid':
            return { text: isExpiringSoon.value ? 'Expiring Soon' : 'Prepaid In-Play', bg: isExpiringSoon.value ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-sky-500/20 text-sky-300 border-sky-500/30' };
        case 'active_postpaid':
            return { text: 'Open Postpaid Tab', bg: 'bg-purple-500/20 text-purple-300 border-purple-500/30' };
        case 'paused':
            return { text: 'TV Suspended (Paused)', bg: 'bg-amber-500/20 text-amber-300 border-amber-500/30' };
        case 'payment_pending':
            return { text: 'TV Standby (Bill Due)', bg: 'bg-amber-500/20 text-amber-300 border-amber-500/40' };
        default:
            return { text: 'Standby', bg: 'bg-slate-800 text-slate-400 border-slate-700' };
    }
});
</script>

<template>
    <div :class="['relative rounded-2xl border transition-all duration-300 p-5 flex flex-col justify-between overflow-hidden', cardTheme]">
        <!-- Top Station Meta Header -->
        <div class="flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-white tracking-wide">
                        {{ station.name }}
                    </span>
                    <span
                        v-if="station.is_vip"
                        class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded bg-gradient-to-r from-amber-500/30 to-amber-600/30 text-amber-300 border border-amber-500/40 shadow-sm"
                    >
                        VIP ★
                    </span>
                </div>

                <!-- TV Physical State Indicator -->
                <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-900/80 border border-slate-800 text-[11px]">
                    <Tv class="w-3 h-3 text-slate-400" />
                    <span
                        :class="[
                            'w-2 h-2 rounded-full',
                            station.tv_physical_state === 'screen_on' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'
                        ]"
                    ></span>
                    <span class="text-[10px] uppercase font-mono text-slate-300">
                        {{ station.tv_physical_state === 'screen_on' ? 'SCREEN ON' : 'STANDBY' }}
                    </span>
                    <span class="text-[9px] uppercase font-bold px-1 rounded bg-slate-800 text-slate-400">
                        {{ station.tv_os_type }}
                    </span>
                </div>
            </div>

            <!-- Status Pill Badge -->
            <div class="flex items-center justify-between">
                <span
                    :class="[
                        'px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide border flex items-center gap-1.5',
                        statusBadge.bg
                    ]"
                >
                    <AlertTriangle v-if="station.is_rogue" class="w-3.5 h-3.5 animate-bounce" />
                    <CheckCircle2 v-else-if="station.current_state === 'available'" class="w-3 h-3" />
                    <Clock v-else-if="station.current_state === 'active_prepaid'" class="w-3 h-3" />
                    <Zap v-else-if="station.current_state === 'active_postpaid'" class="w-3 h-3" />
                    <Pause v-else-if="station.current_state === 'paused'" class="w-3 h-3" />
                    {{ statusBadge.text }}
                </span>

                <span v-if="station.active_session?.customer_name" class="text-xs text-slate-400 truncate max-w-[120px]">
                    👤 {{ station.active_session.customer_name }}
                </span>
            </div>
        </div>

        <!-- Central Timer & Live Billing Section -->
        <div class="my-3 py-3 px-4 rounded-xl bg-[#090d16]/80 border border-slate-800/80 text-center flex flex-col justify-center items-center">
            <!-- Rogue Play Alert Banner -->
            <template v-if="station.is_rogue">
                <p class="text-xs font-bold text-rose-400 uppercase tracking-wide mb-1 flex items-center gap-1">
                    <ShieldAlert class="w-3.5 h-3.5" /> Unauthorized TV Power-On
                </p>
                <div class="text-3xl font-mono font-extrabold text-rose-300 tracking-tight tabular-nums">
                    {{ timerDisplay }}
                </div>
                <p class="text-[11px] text-slate-400 mt-1">TV turned on with remote (No Tab Active)</p>
            </template>

            <!-- Available Idle Station -->
            <template v-else-if="station.current_state === 'available'">
                <p class="text-xs text-emerald-400/90 font-semibold mb-1">Station Clean & TV Standby</p>
                <div class="text-3xl font-mono font-extrabold text-slate-500 tracking-tight tabular-nums">
                    00:00:00
                </div>
                <p class="text-xs text-slate-400 mt-1">PS5 powered in background — Ready to wake TV</p>
            </template>

            <!-- Active / Paused / Payment Pending Session -->
            <template v-else-if="station.active_session">
                <div class="w-full flex items-center justify-between text-xs text-slate-400 mb-1 font-medium">
                    <span class="flex items-center gap-1">
                        <Users class="w-3.5 h-3.5 text-sky-400" />
                        {{ station.active_session.current_tier?.name ?? 'Standard Tier' }}
                    </span>
                    <span class="text-slate-300 font-mono">
                        {{ station.active_session.current_tier?.rate_per_hour_lyd.toFixed(3) }} LYD/hr
                    </span>
                </div>

                <!-- Clock Display -->
                <div
                    :class="[
                        'text-3xl sm:text-4xl font-mono font-extrabold tracking-tight tabular-nums my-1',
                        isExpiringSoon ? 'text-amber-400 animate-pulse' : (station.current_state === 'paused' ? 'text-amber-300' : 'text-white')
                    ]"
                >
                    {{ timerDisplay }}
                </div>

                <!-- Running Financial Total in LYD -->
                <div class="w-full flex items-center justify-between pt-2 mt-1 border-t border-slate-800/80 text-xs">
                    <span class="text-slate-400">
                        Running Bill:
                    </span>
                    <span class="text-sm font-bold text-sky-400 font-mono tabular-nums">
                        {{ station.active_session.final_total_lyd.toFixed(3) }} LYD
                    </span>
                </div>

                <!-- Retail items tag if any -->
                <div v-if="station.active_session.order_items?.length" class="w-full text-left mt-1 text-[11px] text-purple-300/90 truncate">
                    🥤 +{{ station.active_session.order_items.reduce((acc, i) => acc + i.quantity, 0) }} Retail Snacks/Drinks
                </div>
            </template>
        </div>

        <!-- Dynamic Action Toolbar -->
        <div class="pt-2 flex flex-col gap-2">
            <!-- 1. ROGUE PLAY ACTIONS -->
            <div v-if="station.is_rogue" class="grid grid-cols-2 gap-2">
                <button
                    @click="emit('claim-rogue', station)"
                    type="button"
                    class="py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <Play class="w-3.5 h-3.5" /> Start Tab Here
                </button>
                <button
                    @click="emit('force-sleep', station)"
                    type="button"
                    class="py-2.5 px-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <Moon class="w-3.5 h-3.5" /> Force Blackout
                </button>
            </div>

            <!-- 2. AVAILABLE STATION ACTION -->
            <div v-else-if="station.current_state === 'available'">
                <button
                    @click="emit('start-session', station)"
                    type="button"
                    class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-lg shadow-emerald-950/40 transition flex items-center justify-center gap-2 cursor-pointer group"
                >
                    <Play class="w-4 h-4 group-hover:scale-110 transition-transform fill-current" />
                    Start Session (Wake TV)
                </button>
            </div>

            <!-- 3. ACTIVE PREPAID ACTIONS -->
            <div v-else-if="station.current_state === 'active_prepaid'" class="flex flex-col gap-2">
                <!-- Preset Extensions -->
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        @click="emit('extend-time', station, 15)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-slate-800 hover:bg-sky-600 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition cursor-pointer"
                    >
                        +15m
                    </button>
                    <button
                        @click="emit('extend-time', station, 30)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-slate-800 hover:bg-sky-600 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition cursor-pointer"
                    >
                        +30m
                    </button>
                    <button
                        @click="emit('extend-time', station, 60)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-slate-800 hover:bg-sky-600 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition cursor-pointer"
                    >
                        +1h
                    </button>
                </div>

                <!-- Secondary Actions -->
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        @click="emit('switch-tier', station)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 text-[11px] font-medium border border-slate-800 transition flex items-center justify-center gap-1 cursor-pointer"
                        title="Switch Controller Count"
                    >
                        <Users class="w-3 h-3 text-sky-400" /> Tier
                    </button>
                    <button
                        @click="emit('add-retail-item', station)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 text-[11px] font-medium border border-slate-800 transition flex items-center justify-center gap-1 cursor-pointer"
                        title="Add Drinks / Snacks"
                    >
                        <Coffee class="w-3 h-3 text-purple-400" /> POS
                    </button>
                    <button
                        @click="emit('end-session', station)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-rose-950/40 hover:bg-rose-600 text-rose-300 hover:text-white text-[11px] font-bold border border-rose-800/50 transition flex items-center justify-center gap-1 cursor-pointer"
                    >
                        End Tab
                    </button>
                </div>
            </div>

            <!-- 4. ACTIVE POSTPAID ACTIONS -->
            <div v-else-if="station.current_state === 'active_postpaid'" class="flex flex-col gap-2">
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        @click="emit('switch-tier', station)"
                        type="button"
                        class="py-2 px-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition flex items-center justify-center gap-1 cursor-pointer"
                    >
                        <Users class="w-3.5 h-3.5 text-purple-400" /> Tier
                    </button>
                    <button
                        @click="emit('add-retail-item', station)"
                        type="button"
                        class="py-2 px-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition flex items-center justify-center gap-1 cursor-pointer"
                    >
                        <Coffee class="w-3.5 h-3.5 text-purple-400" /> +Snack
                    </button>
                    <button
                        @click="emit('pause-session', station)"
                        type="button"
                        class="py-2 px-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition flex items-center justify-center gap-1 cursor-pointer"
                    >
                        <Pause class="w-3.5 h-3.5 text-amber-400" /> Pause
                    </button>
                </div>

                <button
                    @click="emit('end-session', station)"
                    type="button"
                    class="w-full py-2.5 px-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer"
                >
                    <Moon class="w-3.5 h-3.5" /> Stop & Invoice Tab
                </button>
            </div>

            <!-- 5. PAUSED ACTIONS -->
            <div v-else-if="station.current_state === 'paused'" class="grid grid-cols-2 gap-2">
                <button
                    @click="emit('resume-session', station)"
                    type="button"
                    class="py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <Play class="w-3.5 h-3.5 fill-current" /> Resume Play
                </button>
                <button
                    @click="emit('end-session', station)"
                    type="button"
                    class="py-2.5 px-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    Close Tab
                </button>
            </div>

            <!-- 6. PAYMENT PENDING ACTION -->
            <div v-else-if="station.current_state === 'payment_pending'">
                <button
                    @click="emit('settle-payment', station)"
                    type="button"
                    class="w-full py-3 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs uppercase tracking-wider shadow-lg shadow-amber-950/40 transition flex items-center justify-center gap-2 cursor-pointer"
                >
                    <DollarSign class="w-4 h-4" />
                    Collect & Print Invoice
                </button>
            </div>
        </div>

        <!-- Transfer Option (Available when Active) -->
        <div v-if="['active_prepaid', 'active_postpaid', 'paused'].includes(station.current_state)" class="pt-2 border-t border-slate-800/80 mt-2 flex justify-between items-center text-[11px] text-slate-400">
            <span>Move session:</span>
            <button
                @click="emit('transfer-station', station)"
                type="button"
                class="hover:text-white flex items-center gap-1 font-semibold transition cursor-pointer"
            >
                <ArrowRightLeft class="w-3 h-3 text-sky-400" /> Transfer
            </button>
        </div>
    </div>
</template>
