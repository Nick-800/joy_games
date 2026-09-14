<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import {
    Gamepad2, Settings, Plus, Edit2, Trash2, Check, X,
    Tv, ShieldAlert, Sparkles, DollarSign, Info, ArrowLeft,
    Power, AlertCircle, Layers
} from 'lucide-vue-next';

interface StationItem {
    id: number;
    name: string;
    station_number: number;
    type: 'standard' | 'vip';
    is_vip: boolean;
    default_hourly_rate_millimes: number;
    default_hourly_rate_lyd: number;
    hourly_rate_1_2_lyd?: number | null;
    hourly_rate_3_4_lyd?: number | null;
    available_games: string[];
    is_active: boolean;
    current_state: string;
    has_active_session: boolean;
}

interface PricingRuleInfo {
    tv_control_enabled: boolean;
    currency_symbol: string;
    currency_code: string;
    vip_multiplier: number;
    grace_period_minutes: number;
    minimum_charge_minutes: number;
}

interface PricingTierItem {
    id: number;
    name: string;
    controller_count_min: number;
    controller_count_max: number;
    hourly_rate_millimes: number;
    hourly_rate_lyd: number;
}

const props = defineProps<{
    stations: StationItem[];
    popularGames: string[];
    pricingRule: PricingRuleInfo;
    pricingTiers: PricingTierItem[];
}>();

const activeTab = ref<'stations' | 'tv_feature' | 'pricing'>('stations');

// Modal states
const showStationModal = ref(false);
const editingStation = ref<StationItem | null>(null);
const customGameInput = ref('');

const stationForm = useForm({
    name: '',
    station_number: 1,
    type: 'standard' as 'standard' | 'vip',
    hourly_rate_1_2_lyd: 10,
    hourly_rate_3_4_lyd: 15,
    default_hourly_rate_lyd: 10,
    available_games: [] as string[],
    is_active: true,
});

// Editing Pricing Tier
const editingTierId = ref<number | null>(null);
const tierEditForm = useForm({
    hourly_rate_lyd: 10,
});

function startEditTier(tier: PricingTierItem) {
    editingTierId.value = tier.id;
    tierEditForm.hourly_rate_lyd = tier.hourly_rate_lyd;
}

function saveTier(tierId: number) {
    tierEditForm.put(`/settings/pricing-tiers/${tierId}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingTierId.value = null;
        },
    });
}

function openAddStationModal() {
    editingStation.value = null;
    const nextNumber = props.stations.length > 0
        ? Math.max(...props.stations.map(s => s.station_number)) + 1
        : 1;

    stationForm.reset();
    stationForm.name = `Station ${nextNumber.toString().padStart(2, '0')}`;
    stationForm.station_number = nextNumber;
    stationForm.type = 'standard';
    stationForm.hourly_rate_1_2_lyd = 10;
    stationForm.hourly_rate_3_4_lyd = 15;
    stationForm.default_hourly_rate_lyd = 10;
    stationForm.available_games = ['EA Sports FC 25', 'Tekken 8', 'Mortal Kombat 1'];
    stationForm.is_active = true;
    showStationModal.value = true;
}

function openEditStationModal(station: StationItem) {
    editingStation.value = station;
    stationForm.name = station.name;
    stationForm.station_number = station.station_number;
    stationForm.type = station.type;
    stationForm.hourly_rate_1_2_lyd = station.hourly_rate_1_2_lyd ?? station.default_hourly_rate_lyd ?? 10;
    stationForm.hourly_rate_3_4_lyd = station.hourly_rate_3_4_lyd ?? 15;
    stationForm.default_hourly_rate_lyd = station.default_hourly_rate_lyd ?? stationForm.hourly_rate_1_2_lyd;
    stationForm.available_games = [...(station.available_games || [])];
    stationForm.is_active = station.is_active;
    showStationModal.value = true;
}

function toggleGame(gameName: string) {
    const idx = stationForm.available_games.indexOf(gameName);
    if (idx >= 0) {
        stationForm.available_games.splice(idx, 1);
    } else {
        stationForm.available_games.push(gameName);
    }
}

function addCustomGame() {
    const trimmed = customGameInput.value.trim();
    if (trimmed && !stationForm.available_games.includes(trimmed)) {
        stationForm.available_games.push(trimmed);
        customGameInput.value = '';
    }
}

function removeGame(gameName: string) {
    stationForm.available_games = stationForm.available_games.filter(g => g !== gameName);
}

function submitStationForm() {
    stationForm.default_hourly_rate_lyd = stationForm.hourly_rate_1_2_lyd;
    if (editingStation.value) {
        stationForm.put(`/settings/stations/${editingStation.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showStationModal.value = false;
            },
        });
    } else {
        stationForm.post('/settings/stations', {
            preserveScroll: true,
            onSuccess: () => {
                showStationModal.value = false;
            },
        });
    }
}

// Deleting station
const stationToDelete = ref<StationItem | null>(null);
const deleteError = ref<string | null>(null);

function confirmDeleteStation(station: StationItem) {
    if (station.has_active_session) {
        deleteError.value = `Cannot delete ${station.name} while an active gaming session is running.`;
        return;
    }
    deleteError.value = null;
    stationToDelete.value = station;
}

function executeDeleteStation() {
    if (!stationToDelete.value) return;

    router.delete(`/settings/stations/${stationToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            stationToDelete.value = null;
        },
        onError: (errors) => {
            deleteError.value = Object.values(errors)[0] as string;
        },
    });
}

// Feature flag toggle
const tvFeatureForm = useForm({
    tv_control_enabled: props.pricingRule.tv_control_enabled,
});

function toggleTvFeature() {
    tvFeatureForm.tv_control_enabled = !props.pricingRule.tv_control_enabled;
    tvFeatureForm.post('/settings/feature-flags', {
        preserveScroll: true,
    });
}

const totalVipCount = computed(() => props.stations.filter(s => s.is_vip).length);
const totalActiveCount = computed(() => props.stations.filter(s => s.is_active).length);
</script>

<template>
    <div class="min-h-screen bg-surface-canvas text-text-primary flex flex-col pb-24">
        <!-- Header -->
        <header class="sticky top-0 z-30 bg-surface-elevated/95 backdrop-blur-md border-b border-surface-border-subtle px-4 lg:px-6 py-3 shadow-sm">
            <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link
                        href="/"
                        class="p-2 rounded-lg bg-surface-overlay border border-surface-border-subtle hover:bg-surface-border text-text-secondary transition cursor-pointer"
                        title="Back to Dashboard"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div class="w-10 h-10 rounded-lg bg-brand-primary flex items-center justify-center">
                        <Settings class="w-6 h-6 text-text-primary" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold tracking-tight">System & Station Settings</h1>
                        <p class="text-xs text-text-muted">Control stations, pricing, available games, and hardware automation</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        href="/reports"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-surface-overlay hover:bg-surface-border text-text-secondary border border-surface-border-subtle transition cursor-pointer"
                    >
                        Revenue Reports
                    </Link>
                    <Link
                        href="/"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-brand-primary hover:bg-brand-primary-hover text-text-primary transition cursor-pointer"
                    >
                        Return to Dashboard
                    </Link>
                </div>
            </div>
        </header>

        <main class="max-w-7xl w-full mx-auto px-4 lg:px-6 py-6 flex-1 flex flex-col gap-6">
            <!-- Tabs Navigation -->
            <div class="flex items-center gap-2 border-b border-surface-border-subtle">
                <button
                    @click="activeTab = 'stations'"
                    type="button"
                    :class="[
                        'px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px flex items-center gap-2 cursor-pointer',
                        activeTab === 'stations'
                            ? 'text-brand-primary border-brand-primary'
                            : 'text-text-muted border-transparent hover:text-text-secondary'
                    ]"
                >
                    <Gamepad2 class="w-4 h-4" />
                    Station Management
                    <span class="px-2 py-0.2 rounded-full text-xs bg-surface-overlay font-mono">
                        {{ stations.length }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'tv_feature'"
                    type="button"
                    :class="[
                        'px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px flex items-center gap-2 cursor-pointer',
                        activeTab === 'tv_feature'
                            ? 'text-brand-primary border-brand-primary'
                            : 'text-text-muted border-transparent hover:text-text-secondary'
                    ]"
                >
                    <Tv class="w-4 h-4" />
                    TV Control Automation Flag
                    <span
                        :class="[
                            'px-2 py-0.2 rounded-full text-xs font-semibold',
                            pricingRule.tv_control_enabled
                                ? 'bg-status-available/20 text-status-available'
                                : 'bg-surface-overlay text-text-muted'
                        ]"
                    >
                        {{ pricingRule.tv_control_enabled ? 'Active' : 'Disabled' }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'pricing'"
                    type="button"
                    :class="[
                        'px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px flex items-center gap-2 cursor-pointer',
                        activeTab === 'pricing'
                            ? 'text-brand-primary border-brand-primary'
                            : 'text-text-muted border-transparent hover:text-text-secondary'
                    ]"
                >
                    <DollarSign class="w-4 h-4" />
                    5 LYD Rounding & Rules
                </button>
            </div>

            <!-- SECTION 1: STATIONS MANAGEMENT -->
            <section v-if="activeTab === 'stations'" class="flex flex-col gap-5">
                <!-- Summary bar and Add button -->
                <div class="flex flex-wrap items-center justify-between gap-4 bg-surface-card border border-surface-border-subtle p-4 rounded-2xl">
                    <div class="flex items-center gap-4 text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="text-text-muted">Total Stations:</span>
                            <span class="font-bold text-text-primary text-sm">{{ stations.length }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-text-muted">VIP Suites:</span>
                            <span class="font-bold text-status-warning text-sm">{{ totalVipCount }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-text-muted">Active in Rotation:</span>
                            <span class="font-bold text-status-available text-sm">{{ totalActiveCount }}</span>
                        </div>
                    </div>

                    <button
                        @click="openAddStationModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-brand-primary hover:bg-brand-primary-hover text-text-primary text-xs font-semibold flex items-center gap-2 transition cursor-pointer shadow-sm"
                    >
                        <Plus class="w-4 h-4" />
                        Add New Station
                    </button>
                </div>

                <div v-if="deleteError" class="p-3 rounded-xl bg-status-rogue/10 border border-status-rogue/30 text-status-rogue text-xs flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" />
                    <span>{{ deleteError }}</span>
                </div>

                <!-- Stations Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="station in stations"
                        :key="station.id"
                        class="bg-surface-card border border-surface-border-subtle rounded-2xl p-5 flex flex-col justify-between gap-4 transition hover:border-surface-border shadow-sm"
                    >
                        <div class="flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold text-text-primary">
                                            {{ station.name }}
                                        </h3>
                                        <span
                                            v-if="station.is_vip"
                                            class="px-2 py-0.5 text-xs font-semibold uppercase tracking-wider rounded-full bg-status-warning/15 text-status-warning border border-status-warning/40 flex items-center gap-1"
                                        >
                                            <Sparkles class="w-3 h-3" />
                                            VIP
                                        </span>
                                    </div>
                                    <span class="text-xs text-text-muted font-mono">
                                        Station #{{ station.station_number }} · {{ station.type.toUpperCase() }}
                                    </span>
                                </div>

                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs rounded-full font-semibold',
                                        station.is_active
                                            ? 'bg-status-available/10 text-status-available border border-status-available/30'
                                            : 'bg-surface-overlay text-text-muted border border-surface-border-subtle'
                                    ]"
                                >
                                    {{ station.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </div>

                            <!-- Rates Card (1-2 Players & 3-4 Players) -->
                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-2.5 rounded-xl bg-surface-canvas/80 border border-surface-border-subtle flex flex-col justify-between">
                                    <span class="text-[11px] text-text-muted font-medium">1–2 Players (Solo/Duo)</span>
                                    <span class="text-sm font-bold font-mono text-status-available mt-1">
                                        {{ station.hourly_rate_1_2_lyd ?? station.default_hourly_rate_lyd }}
                                        <span class="text-[10px] text-text-muted font-normal">LYD/hr</span>
                                    </span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-surface-canvas/80 border border-surface-border-subtle flex flex-col justify-between">
                                    <span class="text-[11px] text-text-muted font-medium">3–4 Players (Squad)</span>
                                    <span class="text-sm font-bold font-mono text-brand-primary mt-1">
                                        {{ station.hourly_rate_3_4_lyd ?? 'Default' }}
                                        <span v-if="station.hourly_rate_3_4_lyd" class="text-[10px] text-text-muted font-normal">LYD/hr</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Available Games List -->
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-text-muted mb-2 flex items-center justify-between">
                                    <span>Installed Games</span>
                                    <span class="text-text-muted font-mono font-normal">({{ (station.available_games || []).length }})</span>
                                </div>

                                <div v-if="station.available_games && station.available_games.length > 0" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="game in station.available_games"
                                        :key="game"
                                        class="px-2 py-1 rounded-lg text-xs bg-surface-overlay text-text-secondary border border-surface-border-subtle font-medium"
                                    >
                                        {{ game }}
                                    </span>
                                </div>
                                <div v-else class="text-xs text-text-muted italic">
                                    No specific games listed. All standard library titles available.
                                </div>
                            </div>
                        </div>

                        <!-- Action buttons footer -->
                        <div class="pt-3 border-t border-surface-border-subtle flex items-center justify-between gap-2">
                            <span v-if="station.has_active_session" class="text-xs font-semibold text-status-prepaid">
                                ● In-Play Session
                            </span>
                            <span v-else class="text-xs text-text-muted">
                                Ready for play
                            </span>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="openEditStationModal(station)"
                                    type="button"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-surface-overlay hover:bg-surface-border text-text-secondary border border-surface-border-subtle transition flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Edit2 class="w-3.5 h-3.5" />
                                    Edit
                                </button>
                                <button
                                    @click="confirmDeleteStation(station)"
                                    type="button"
                                    :disabled="station.has_active_session"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-surface-overlay hover:bg-status-rogue/20 text-status-rogue border border-status-rogue/30 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                                    title="Delete station"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: TV CONTROL AUTOMATION FEATURE FLAG -->
            <section v-if="activeTab === 'tv_feature'" class="flex flex-col gap-5">
                <div class="bg-surface-card border border-surface-border-subtle rounded-2xl p-6 flex flex-col gap-6 max-w-3xl">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-12 h-12 rounded-xl bg-surface-overlay flex items-center justify-center text-brand-primary shrink-0 border border-surface-border-subtle">
                                <Tv class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-text-primary">
                                    Smart TV Hardware Automation & Control
                                </h3>
                                <p class="text-xs text-text-muted mt-1 leading-relaxed">
                                    Controls whether Joy Games sends network commands (Wake-on-LAN and standby power signals) to lounge smart TVs, runs background power status pinging, and displays rogue play alerts.
                                </p>
                            </div>
                        </div>

                        <!-- Toggle Button -->
                        <button
                            @click="toggleTvFeature"
                            type="button"
                            :disabled="tvFeatureForm.processing"
                            :class="[
                                'relative inline-flex h-7 w-14 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:opacity-50',
                                pricingRule.tv_control_enabled ? 'bg-status-available' : 'bg-surface-overlay'
                            ]"
                        >
                            <span
                                :class="[
                                    'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out',
                                    pricingRule.tv_control_enabled ? 'translate-x-7' : 'translate-x-0'
                                ]"
                            />
                        </button>
                    </div>

                    <div
                        :class="[
                            'p-4 rounded-xl border flex items-start gap-3 text-xs',
                            pricingRule.tv_control_enabled
                                ? 'bg-status-available/10 border-status-available/30 text-status-available'
                                : 'bg-surface-overlay border-surface-border-subtle text-text-muted'
                        ]"
                    >
                        <Info class="w-4 h-4 shrink-0 mt-0.5" />
                        <div>
                            <div class="font-semibold text-text-primary text-sm mb-1">
                                Status: {{ pricingRule.tv_control_enabled ? 'AUTOMATION ENABLED' : 'AUTOMATION STOPPED (Pure Manual Mode)' }}
                            </div>
                            <p v-if="pricingRule.tv_control_enabled" class="text-text-secondary leading-relaxed">
                                The application actively communicates with Smart TV hardware (Hisense VIDAA, Android TV, Roku). Starting a session automatically wakes the screen, ending a session powers off the TV, and rogue power-on detection alerts staff if a screen is manually turned on without an active session.
                            </p>
                            <p v-else class="text-text-muted leading-relaxed">
                                The TV control system is paused. No background network commands are transmitted to TVs, station cards display clean operational statuses without hardware indicators, and the Dev Simulator is hidden. All station timekeeping functions normally via manual software timers.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 3: 5 LYD ROUNDING & PRICING RULES -->
            <section v-if="activeTab === 'pricing'" class="flex flex-col gap-5">
                <div class="bg-surface-card border border-surface-border-subtle rounded-2xl p-6 max-w-3xl flex flex-col gap-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-status-available/10 text-status-available flex items-center justify-center border border-status-available/30">
                            <DollarSign class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-text-primary">
                                Libyan Cash Economy: 5 LYD Ceiling Rounding
                            </h3>
                            <p class="text-xs text-text-muted">Strict zero-fractions cash rule applied across all billing calculations</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="p-3.5 rounded-xl bg-surface-canvas/80 border border-surface-border-subtle flex flex-col gap-1.5">
                            <div class="font-semibold text-text-primary">Ceiling Rounding Formula</div>
                            <p class="text-text-muted leading-relaxed">
                                Calculated time amounts and final session totals are rounded up (ceiling, never floor) to the closest multiple of 5 LYD (5, 10, 15, 20, 25, 30...). Fractional values like 1.5 LYD, 6.25 LYD, or 13 LYD are strictly rounded up to 5 LYD, 10 LYD, and 15 LYD respectively.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-3 rounded-xl bg-surface-overlay border border-surface-border-subtle">
                                <span class="text-text-muted block">Grace Period</span>
                                <span class="text-lg font-bold font-mono text-text-primary">
                                    {{ pricingRule.grace_period_minutes }} min
                                </span>
                                <span class="text-[10px] text-text-muted block mt-0.5">Sessions cancelled under this duration are 0 LYD</span>
                            </div>

                            <div class="p-3 rounded-xl bg-surface-overlay border border-surface-border-subtle">
                                <span class="text-text-muted block">Minimum Charge</span>
                                <span class="text-lg font-bold font-mono text-text-primary">
                                    {{ pricingRule.minimum_charge_minutes }} min
                                </span>
                                <span class="text-[10px] text-text-muted block mt-0.5">Applies to postpaid play beyond grace period</span>
                            </div>

                            <div class="p-3 rounded-xl bg-surface-overlay border border-surface-border-subtle">
                                <span class="text-text-muted block">VIP Multiplier</span>
                                <span class="text-lg font-bold font-mono text-status-warning">
                                    {{ pricingRule.vip_multiplier }}x
                                </span>
                                <span class="text-[10px] text-text-muted block mt-0.5">Default rate multiplier for VIP lounges</span>
                            </div>
                        </div>

                        <!-- Global Base Pricing Tiers -->
                        <div class="p-3.5 rounded-xl bg-surface-canvas/80 border border-surface-border-subtle flex flex-col gap-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-text-primary">Global Pricing Tiers (Default Fallback Rates)</div>
                                    <p class="text-[11px] text-text-muted">Rates used for stations that don't have custom 1–2 or 3–4 player pricing configured</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div
                                    v-for="tier in pricingTiers"
                                    :key="tier.id"
                                    class="p-3 rounded-xl bg-surface-overlay border border-surface-border-subtle flex items-center justify-between"
                                >
                                    <div>
                                        <span class="text-text-primary font-semibold block text-xs">{{ tier.name }}</span>
                                        <span class="text-[10px] text-text-muted font-mono">{{ tier.controller_count_min }}–{{ tier.controller_count_max }} Controllers</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div v-if="editingTierId === tier.id" class="flex items-center gap-1">
                                            <input
                                                v-model.number="tierEditForm.hourly_rate_lyd"
                                                type="number"
                                                step="5"
                                                min="5"
                                                class="w-16 bg-surface-canvas border border-brand-primary rounded px-2 py-1 font-mono font-bold text-xs text-text-primary"
                                            />
                                            <button
                                                @click="saveTier(tier.id)"
                                                type="button"
                                                class="p-1 rounded bg-brand-primary hover:bg-brand-primary-hover text-white text-xs cursor-pointer"
                                                title="Save"
                                            >
                                                <Check class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                @click="editingTierId = null"
                                                type="button"
                                                class="p-1 rounded bg-surface-border hover:bg-surface-border-subtle text-text-muted text-xs cursor-pointer"
                                                title="Cancel"
                                            >
                                                <X class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                        <div v-else class="flex items-center gap-2">
                                            <span class="text-sm font-bold font-mono text-text-primary">
                                                {{ tier.hourly_rate_lyd }} <span class="text-[10px] text-text-muted font-normal">LYD/hr</span>
                                            </span>
                                            <button
                                                @click="startEditTier(tier)"
                                                type="button"
                                                class="p-1 rounded bg-surface-card hover:bg-surface-border text-text-muted hover:text-text-primary text-xs cursor-pointer border border-surface-border-subtle"
                                                title="Edit Tier Base Rate"
                                            >
                                                <Edit2 class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- ADD / EDIT STATION MODAL -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showStationModal"
                class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
                @click.self="showStationModal = false"
            >
                <div class="bg-surface-elevated border border-surface-border rounded-2xl max-w-xl w-full p-6 shadow-2xl flex flex-col gap-5 my-8">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-surface-border-subtle pb-3">
                        <div class="flex items-center gap-2.5">
                            <Gamepad2 class="w-5 h-5 text-brand-primary" />
                            <h3 class="text-base font-bold text-text-primary">
                                {{ editingStation ? `Edit ${editingStation.name}` : 'Add New Station' }}
                            </h3>
                        </div>
                        <button
                            @click="showStationModal = false"
                            type="button"
                            class="p-1 rounded-lg text-text-muted hover:text-text-primary transition cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Modal Body Form -->
                    <form @submit.prevent="submitStationForm" class="flex flex-col gap-4 text-xs">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold uppercase text-text-muted mb-1">Station Name</label>
                                <input
                                    v-model="stationForm.name"
                                    type="text"
                                    required
                                    class="w-full bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary px-3 py-2 focus:outline-none focus:border-brand-primary"
                                    placeholder="e.g. Station 09 or VIP Lounge"
                                />
                                <div v-if="stationForm.errors.name" class="text-status-rogue mt-1">
                                    {{ stationForm.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="block font-semibold uppercase text-text-muted mb-1">Station Number</label>
                                <input
                                    v-model.number="stationForm.station_number"
                                    type="number"
                                    min="1"
                                    required
                                    class="w-full bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary px-3 py-2 font-mono focus:outline-none focus:border-brand-primary"
                                />
                                <div v-if="stationForm.errors.station_number" class="text-status-rogue mt-1">
                                    {{ stationForm.errors.station_number }}
                                </div>
                            </div>
                        </div>

                        <!-- Station Type & VIP Definition -->
                        <div>
                            <label class="block font-semibold uppercase text-text-muted mb-1">Station Tier Type</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="stationForm.type = 'standard'"
                                    :class="[
                                        'py-2 px-3 rounded-lg border font-semibold text-center transition cursor-pointer',
                                        stationForm.type === 'standard'
                                            ? 'bg-brand-primary text-text-primary border-transparent'
                                            : 'bg-surface-overlay text-text-muted border-surface-border-subtle'
                                    ]"
                                >
                                    Standard
                                </button>
                                <button
                                    type="button"
                                    @click="stationForm.type = 'vip'"
                                    :class="[
                                        'py-2 px-3 rounded-lg border font-semibold text-center transition cursor-pointer flex items-center justify-center gap-1',
                                        stationForm.type === 'vip'
                                            ? 'bg-status-warning/20 text-status-warning border-status-warning/50'
                                            : 'bg-surface-overlay text-text-muted border-surface-border-subtle'
                                    ]"
                                >
                                    <Sparkles class="w-3 h-3" />
                                    VIP
                                </button>
                            </div>
                        </div>

                        <!-- Player Rates: 1-2 Players & 3-4 Players (Multiples of 5 LYD) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold uppercase text-text-muted mb-1">
                                    1–2 Players Rate (LYD / hr)
                                </label>
                                <input
                                    v-model.number="stationForm.hourly_rate_1_2_lyd"
                                    type="number"
                                    step="5"
                                    min="5"
                                    required
                                    class="w-full bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary px-3 py-2 font-mono font-bold focus:outline-none focus:border-brand-primary"
                                    placeholder="10"
                                />
                                <span class="text-[10px] text-text-muted mt-0.5 block">
                                    Must be multiple of 5 (e.g. 5, 10, 15, 20...)
                                </span>
                                <div v-if="stationForm.errors.hourly_rate_1_2_lyd" class="text-status-rogue mt-1">
                                    {{ stationForm.errors.hourly_rate_1_2_lyd }}
                                </div>
                            </div>

                            <div>
                                <label class="block font-semibold uppercase text-text-muted mb-1">
                                    3–4 Players Rate (LYD / hr)
                                </label>
                                <input
                                    v-model.number="stationForm.hourly_rate_3_4_lyd"
                                    type="number"
                                    step="5"
                                    min="5"
                                    required
                                    class="w-full bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary px-3 py-2 font-mono font-bold focus:outline-none focus:border-brand-primary"
                                    placeholder="15"
                                />
                                <span class="text-[10px] text-text-muted mt-0.5 block">
                                    Must be multiple of 5 (e.g. 5, 10, 15, 20...)
                                </span>
                                <div v-if="stationForm.errors.hourly_rate_3_4_lyd" class="text-status-rogue mt-1">
                                    {{ stationForm.errors.hourly_rate_3_4_lyd }}
                                </div>
                            </div>
                        </div>

                        <!-- Available Games List Section -->
                        <div class="flex flex-col gap-2 pt-2 border-t border-surface-border-subtle">
                            <label class="block font-semibold uppercase text-text-muted">
                                Available Games on Station
                            </label>

                            <!-- Popular game quick toggles -->
                            <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto p-2 bg-surface-canvas rounded-xl border border-surface-border-subtle">
                                <button
                                    v-for="game in popularGames"
                                    :key="game"
                                    type="button"
                                    @click="toggleGame(game)"
                                    :class="[
                                        'px-2.5 py-1 rounded-lg text-xs font-semibold transition border cursor-pointer',
                                        stationForm.available_games.includes(game)
                                            ? 'bg-status-available text-surface-canvas border-transparent'
                                            : 'bg-surface-overlay text-text-secondary border-surface-border-subtle hover:text-text-primary'
                                    ]"
                                >
                                    {{ game }}
                                </button>
                            </div>

                            <!-- Custom game adder -->
                            <div class="flex items-center gap-2 mt-1">
                                <input
                                    v-model="customGameInput"
                                    type="text"
                                    class="flex-1 bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary px-3 py-1.5 focus:outline-none focus:border-brand-primary"
                                    placeholder="Add custom game title..."
                                    @keydown.enter.prevent="addCustomGame"
                                />
                                <button
                                    @click="addCustomGame"
                                    type="button"
                                    class="px-3 py-1.5 rounded-lg bg-surface-overlay hover:bg-surface-border text-text-secondary border border-surface-border-subtle font-semibold transition cursor-pointer"
                                >
                                    + Add
                                </button>
                            </div>

                            <!-- Selected games chips -->
                            <div class="flex flex-wrap gap-1 mt-1">
                                <span
                                    v-for="game in stationForm.available_games"
                                    :key="game"
                                    class="px-2 py-0.5 rounded-md text-xs bg-brand-primary/10 border border-brand-primary/30 text-brand-primary font-medium flex items-center gap-1"
                                >
                                    {{ game }}
                                    <button
                                        type="button"
                                        @click="removeGame(game)"
                                        class="hover:text-text-primary cursor-pointer ml-1"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                        </div>

                        <!-- Active Switcher -->
                        <div class="flex items-center gap-3 pt-2 border-t border-surface-border-subtle">
                            <input
                                v-model="stationForm.is_active"
                                type="checkbox"
                                id="station-active-toggle"
                                class="w-4 h-4 rounded text-brand-primary focus:ring-0 bg-surface-canvas border-surface-border-subtle cursor-pointer"
                            />
                            <label for="station-active-toggle" class="font-semibold text-text-primary cursor-pointer">
                                Active Station (visible on Dashboard for play)
                            </label>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-surface-border-subtle mt-2">
                            <button
                                @click="showStationModal = false"
                                type="button"
                                class="px-4 py-2 rounded-xl bg-surface-overlay hover:bg-surface-border text-text-secondary transition font-semibold cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="stationForm.processing"
                                class="px-5 py-2 rounded-xl bg-brand-primary hover:bg-brand-primary-hover text-text-primary transition font-semibold disabled:opacity-50 cursor-pointer shadow-sm"
                            >
                                {{ editingStation ? 'Save Changes' : 'Create Station' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- DELETE CONFIRMATION MODAL -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="stationToDelete"
                class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4"
                @click.self="stationToDelete = null"
            >
                <div class="bg-surface-elevated border border-surface-border rounded-2xl max-w-md w-full p-6 shadow-2xl flex flex-col gap-4">
                    <div class="flex items-center gap-3 text-status-rogue">
                        <AlertCircle class="w-6 h-6 shrink-0" />
                        <h3 class="text-base font-bold text-text-primary">Delete Station</h3>
                    </div>

                    <p class="text-xs text-text-muted leading-relaxed">
                        Are you sure you want to delete <strong class="text-text-primary">{{ stationToDelete.name }}</strong> (#{{ stationToDelete.station_number }})? This action cannot be undone.
                    </p>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-surface-border-subtle">
                        <button
                            @click="stationToDelete = null"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-surface-overlay hover:bg-surface-border text-text-secondary text-xs font-semibold transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            @click="executeDeleteStation"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-status-rogue hover:brightness-110 text-text-primary text-xs font-semibold transition cursor-pointer"
                        >
                            Delete Station
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
