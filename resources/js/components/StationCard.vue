<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { 
    Tv, Clock, Zap, TriangleAlert, Play, Plus, 
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
        return 'border-status-rogue/80 bg-status-rogue/10 animate-rogue-pulse';
    }

    switch (props.station.current_state) {
        case 'available':
            return 'border-status-available/40 bg-surface-card hover:border-status-available/70 shadow-md';
        case 'active_prepaid':
            if (isExpiringSoon.value) {
                return 'border-status-warning/70 bg-surface-card';
            }
            return 'border-status-prepaid/50 bg-surface-card';
        case 'active_postpaid':
            return 'border-status-postpaid/50 bg-surface-card';
        case 'paused':
            return 'border-status-paused/50 bg-surface-card shadow-md';
        case 'payment_pending':
            return 'border-status-warning/80 bg-surface-card shadow-md';
        default:
            return 'border-surface-border bg-surface-elevated/50';
    }
});

const statusBadge = computed(() => {
    if (props.station.is_rogue) {
        return { text: 'UNAUTHORIZED TV ON', bg: 'bg-status-rogue/15 text-status-rogue border-status-rogue/40' };
    }

    switch (props.station.current_state) {
        case 'available':
            return { text: 'TV OFF (Ready)', bg: 'bg-status-available/10 text-status-available border-status-available/30' };
        case 'active_prepaid':
            return { text: isExpiringSoon.value ? 'Expiring Soon' : 'Prepaid In-Play', bg: isExpiringSoon.value ? 'bg-status-warning/15 text-status-warning border-status-warning/40' : 'bg-status-prepaid/15 text-status-prepaid border-status-prepaid/30' };
        case 'active_postpaid':
            return { text: 'Open Postpaid Tab', bg: 'bg-status-postpaid/15 text-status-postpaid border-status-postpaid/30' };
        case 'paused':
            return { text: 'TV Suspended (Paused)', bg: 'bg-status-paused/15 text-status-paused border-status-paused/30' };
        case 'payment_pending':
            return { text: 'TV Standby (Bill Due)', bg: 'bg-status-warning/15 text-status-warning border-status-warning/40' };
        default:
            return { text: 'Standby', bg: 'bg-surface-overlay text-text-muted border-surface-border-subtle' };
    }
});
</script>

<template>
    <div :class="['relative rounded-2xl border transition-all duration-300 p-5 flex flex-col justify-between overflow-hidden', cardTheme]">
        <!-- Top Station Meta Header -->
        <div class="flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-text-primary tracking-wide">
                        {{ station.name }}
                    </span>
                    <span
                        v-if="station.is_vip"
                        class="px-2 py-0.5 text-xs font-semibold uppercase tracking-wide rounded-full border border-status-warning/40 bg-status-warning/10 text-status-warning"
                    >
                        VIP
                    </span>
                </div>

                <!-- TV Physical State Indicator -->
                <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-surface-overlay border border-surface-border-subtle text-xs">
                    <Tv class="w-3 h-3 text-text-muted" />
                    <span
                        :class="[
                            'w-2 h-2 rounded-full',
                            station.tv_physical_state === 'screen_on' ? 'bg-status-available' : 'bg-text-muted'
                        ]"
                    ></span>
                    <span class="text-xs uppercase font-mono text-text-secondary">
                        {{ station.tv_physical_state === 'screen_on' ? 'SCREEN ON' : 'STANDBY' }}
                    </span>
                    <span class="text-xs uppercase font-semibold px-1 rounded bg-surface-elevated text-text-muted">
                        {{ station.tv_os_type }}
                    </span>
                </div>
            </div>

            <!-- Status Pill Badge -->
            <div class="flex items-center justify-between">
                <span
                    :class="[
                        'px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide border flex items-center gap-1.5',
                        statusBadge.bg
                    ]"
                >
                    <TriangleAlert v-if="station.is_rogue" class="w-3.5 h-3.5" />
                    <CheckCircle2 v-else-if="station.current_state === 'available'" class="w-3 h-3" />
                    <Clock v-else-if="station.current_state === 'active_prepaid'" class="w-3 h-3" />
                    <Zap v-else-if="station.current_state === 'active_postpaid'" class="w-3 h-3" />
                    <Pause v-else-if="station.current_state === 'paused'" class="w-3 h-3" />
                    {{ statusBadge.text }}
                </span>

                <span v-if="station.active_session?.customer_name" class="text-xs text-text-muted truncate max-w-[120px]">
                    {{ station.active_session.customer_name }}
                </span>
            </div>
        </div>

        <!-- Central Timer & Live Billing Section -->
        <div class="my-3 py-3 px-4 rounded-xl bg-surface-canvas/80 border border-surface-border-subtle text-center flex flex-col justify-center items-center">
            <!-- Rogue Play Alert Banner -->
            <template v-if="station.is_rogue">
                <p class="text-xs font-semibold text-status-rogue uppercase tracking-wide mb-1 flex items-center gap-1">
                    <ShieldAlert class="w-3.5 h-3.5" /> Unauthorized TV Power-On
                </p>
                <div class="text-3xl font-mono font-bold text-status-rogue tracking-tight tabular-nums">
                    {{ timerDisplay }}
                </div>
                <p class="text-xs text-text-muted mt-1">TV turned on with remote (No Tab Active)</p>
            </template>

            <!-- Available Idle Station -->
            <template v-else-if="station.current_state === 'available'">
                <p class="text-xs text-status-available font-semibold mb-1">Ready to start</p>
                <div class="text-3xl font-mono font-bold text-text-primary tracking-tight tabular-nums">
                    00:00:00
                </div>
            </template>

            <!-- Active / Paused / Payment Pending Session -->
            <template v-else-if="station.active_session">
                <div class="w-full flex items-center justify-between text-xs mb-1 font-medium">
                    <span class="flex items-center gap-1 text-text-secondary">
                        <Users class="w-3.5 h-3.5 text-text-secondary" />
                        {{ station.active_session.current_tier?.name ?? 'Standard Tier' }}
                    </span>
                    <span class="text-text-muted font-mono">
                        {{ station.active_session.current_tier?.rate_per_hour_lyd.toFixed(3) }} LYD/hr
                    </span>
                </div>

                <!-- Clock Display -->
                <div
                    :class="[
                        'text-3xl sm:text-4xl font-mono font-bold tracking-tight tabular-nums my-1',
                        isExpiringSoon ? 'text-status-warning' : (station.current_state === 'paused' ? 'text-status-paused' : 'text-text-primary')
                    ]"
                >
                    {{ timerDisplay }}
                </div>

                <!-- Running Financial Total in LYD -->
                <div class="w-full flex items-center justify-between pt-2 mt-1 border-t border-surface-border-subtle text-xs">
                    <span class="text-text-muted">
                        Running Bill:
                    </span>
                    <span class="text-sm font-semibold text-text-primary font-mono tabular-nums">
                        {{ station.active_session.final_total_lyd.toFixed(3) }} LYD
                    </span>
                </div>

                <!-- Retail items tag if any -->
                <div v-if="station.active_session.order_items?.length" class="w-full text-left mt-1 text-xs text-text-secondary truncate">
                    +{{ station.active_session.order_items.reduce((acc, i) => acc + i.quantity, 0) }} items
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
                    class="py-2.5 px-3 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary font-semibold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <Play class="w-3.5 h-3.5" /> Start Tab Here
                </button>
                <button
                    @click="emit('force-sleep', station)"
                    type="button"
                    class="py-2.5 px-3 rounded-lg bg-status-rogue hover:brightness-110 text-text-primary font-semibold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <Moon class="w-3.5 h-3.5" /> Force Blackout
                </button>
            </div>

            <!-- 2. AVAILABLE STATION ACTION -->
            <div v-else-if="station.current_state === 'available'">
                <button
                    @click="emit('start-session', station)"
                    type="button"
                    class="w-full py-3 px-4 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary font-semibold text-sm transition flex items-center justify-center gap-2 cursor-pointer"
                >
                    <Play class="w-4 h-4 fill-current" />
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
                        class="py-1.5 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition cursor-pointer"
                    >
                        +15m
                    </button>
                    <button
                        @click="emit('extend-time', station, 30)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition cursor-pointer"
                    >
                        +30m
                    </button>
                    <button
                        @click="emit('extend-time', station, 60)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition cursor-pointer"
                    >
                        +1h
                    </button>
                </div>

                <!-- Secondary Actions -->
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        @click="emit('switch-tier', station)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-medium border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                        title="Switch Controller Count"
                    >
                        <Users class="w-3 h-3" /> Tier
                    </button>
                    <button
                        @click="emit('add-retail-item', station)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-medium border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                        title="Add Drinks / Snacks"
                    >
                        <Coffee class="w-3 h-3" /> POS
                    </button>
                    <button
                        @click="emit('end-session', station)"
                        type="button"
                        class="py-1.5 px-2 rounded-lg border border-status-rogue/50 text-status-rogue hover:bg-status-rogue hover:text-text-primary text-xs font-semibold transition flex items-center justify-center gap-1 cursor-pointer"
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
                        class="py-2 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                    >
                        <Users class="w-3.5 h-3.5" /> Tier
                    </button>
                    <button
                        @click="emit('add-retail-item', station)"
                        type="button"
                        class="py-2 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                    >
                        <Coffee class="w-3.5 h-3.5" /> +Snack
                    </button>
                    <button
                        @click="emit('pause-session', station)"
                        type="button"
                        class="py-2 px-2 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary text-xs font-semibold border border-surface-border-subtle transition flex items-center justify-center gap-1 cursor-pointer"
                    >
                        <Pause class="w-3.5 h-3.5" /> Pause
                    </button>
                </div>

                <button
                    @click="emit('end-session', station)"
                    type="button"
                    class="w-full py-2.5 px-3 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary font-semibold text-xs transition flex items-center justify-center gap-2 cursor-pointer"
                >
                    <Moon class="w-3.5 h-3.5" /> Stop & Invoice Tab
                </button>
            </div>

            <!-- 5. PAUSED ACTIONS -->
            <div v-else-if="station.current_state === 'paused'" class="grid grid-cols-2 gap-2">
                <button
                    @click="emit('resume-session', station)"
                    type="button"
                    class="py-2.5 px-3 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary font-semibold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <Play class="w-3.5 h-3.5 fill-current" /> Resume Play
                </button>
                <button
                    @click="emit('end-session', station)"
                    type="button"
                    class="py-2.5 px-3 rounded-lg border border-status-rogue/50 text-status-rogue hover:bg-status-rogue hover:text-text-primary font-semibold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    Close Tab
                </button>
            </div>

            <!-- 6. PAYMENT PENDING ACTION -->
            <div v-else-if="station.current_state === 'payment_pending'">
                <button
                    @click="emit('settle-payment', station)"
                    type="button"
                    class="w-full py-3 px-4 rounded-lg bg-status-warning text-surface-canvas font-semibold text-xs uppercase tracking-wide transition flex items-center justify-center gap-2 cursor-pointer"
                >
                    <DollarSign class="w-4 h-4" />
                    Collect & Print Invoice
                </button>
            </div>
        </div>

        <!-- Transfer Option (Available when Active) -->
        <div v-if="['active_prepaid', 'active_postpaid', 'paused'].includes(station.current_state)" class="pt-2 border-t border-surface-border-subtle mt-2 flex justify-between items-center text-xs text-text-muted">
            <span>Move session:</span>
            <button
                @click="emit('transfer-station', station)"
                type="button"
                class="hover:text-text-primary flex items-center gap-1 font-semibold transition cursor-pointer"
            >
                <ArrowRightLeft class="w-3 h-3" /> Transfer
            </button>
        </div>
    </div>
</template>
