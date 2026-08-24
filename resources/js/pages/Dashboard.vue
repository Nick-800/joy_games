<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePoll, router } from '@inertiajs/vue3';
import ShiftHeaderBar from '../components/ShiftHeaderBar.vue';
import StationCard, { type StationData } from '../components/StationCard.vue';
import StartSessionModal from '../components/modals/StartSessionModal.vue';
import AddRetailItemModal from '../components/modals/AddRetailItemModal.vue';
import SwitchTierModal from '../components/modals/SwitchTierModal.vue';
import CheckoutInvoiceModal from '../components/modals/CheckoutInvoiceModal.vue';
import ShiftModal from '../components/modals/ShiftModal.vue';
import PinSwitchModal from '../components/modals/PinSwitchModal.vue';
import TransferModal from '../components/modals/TransferModal.vue';
import HardwareSimulatorDrawer from '../components/simulator/HardwareSimulatorDrawer.vue';
import { TriangleAlert } from 'lucide-vue-next';

interface PricingTierItem {
    id: number;
    name: string;
    controller_count_min: number;
    controller_count_max: number;
    hourly_rate_millimes: number;
    hourly_rate_lyd: number;
}

interface ProductItem {
    id: number;
    name: string;
    category: string;
    price_millimes: number;
    price_lyd: number;
    stock_quantity: number;
}

const props = defineProps<{
    stations: StationData[];
    pricingTiers: PricingTierItem[];
    pricingRule: {
        grace_period_minutes: number;
        minimum_charge_minutes: number;
        rounding_step_minutes: number;
        vip_multiplier: number;
        currency_code: string;
        currency_symbol: string;
    };
    products: ProductItem[];
    activeShift: any;
    staffUsers: any[];
    serverTime: string;
}>();

// Poll background updates every 4 seconds
usePoll(4000);

// Filter tabs
const activeFilter = ref<'all' | 'active' | 'available' | 'rogue'>('all');

const filteredStations = computed(() => {
    if (activeFilter.value === 'active') {
        return props.stations.filter(s => ['active_prepaid', 'active_postpaid', 'paused', 'payment_pending'].includes(s.current_state));
    }
    if (activeFilter.value === 'available') {
        return props.stations.filter(s => s.current_state === 'available' && !s.is_rogue);
    }
    if (activeFilter.value === 'rogue') {
        return props.stations.filter(s => s.is_rogue);
    }
    return props.stations;
});

const activeStationsCount = computed(() => {
    return props.stations.filter(s => ['active_prepaid', 'active_postpaid', 'paused', 'payment_pending'].includes(s.current_state)).length;
});

const rogueCount = computed(() => {
    return props.stations.filter(s => s.is_rogue).length;
});

// Modal State Management
const selectedStation = ref<StationData | null>(null);
const showStartModal = ref(false);
const initialBackdateMinutes = ref(0);
const showRetailModal = ref(false);
const showTierModal = ref(false);
const showCheckoutModal = ref(false);
const showShiftModal = ref(false);
const showPinModal = ref(false);
const showTransferModal = ref(false);
const simulatorOpen = ref(true); // Open by default for development

function onStartSession(station: StationData) {
    selectedStation.value = station;
    initialBackdateMinutes.value = 0;
    showStartModal.value = true;
}

function onClaimRogue(station: StationData) {
    selectedStation.value = station;
    initialBackdateMinutes.value = Math.ceil(station.rogue_duration_seconds / 60);
    showStartModal.value = true;
}

function onAddRetailItem(station: StationData) {
    selectedStation.value = station;
    showRetailModal.value = true;
}

function onSwitchTier(station: StationData) {
    selectedStation.value = station;
    showTierModal.value = true;
}

function onSettlePayment(station: StationData) {
    selectedStation.value = station;
    showCheckoutModal.value = true;
}

function onTransferStation(station: StationData) {
    selectedStation.value = station;
    showTransferModal.value = true;
}

function onExtendTime(station: StationData, minutes: number) {
    if (!station.active_session) return;
    router.post(`/sessions/${station.active_session.id}/extend`, { minutes }, { preserveScroll: true });
}

function onPauseSession(station: StationData) {
    if (!station.active_session) return;
    router.post(`/sessions/${station.active_session.id}/pause`, {}, { preserveScroll: true });
}

function onResumeSession(station: StationData) {
    if (!station.active_session) return;
    router.post(`/sessions/${station.active_session.id}/resume`, {}, { preserveScroll: true });
}

function onEndSession(station: StationData) {
    if (!station.active_session) return;
    router.post(`/sessions/${station.active_session.id}/end`, {}, { preserveScroll: true });
}

function onForceSleep(station: StationData) {
    router.post(`/simulator/stations/${station.id}/sleep`, {}, { preserveScroll: true });
}
</script>

<template>
    <div class="min-h-screen bg-surface-canvas text-text-primary flex flex-col selection:bg-brand-primary selection:text-text-primary pb-36">
        <!-- Top Operational Header Bar -->
        <ShiftHeaderBar
            :active-shift="activeShift"
            :active-stations-count="activeStationsCount"
            :total-stations-count="stations.length"
            :simulator-open="simulatorOpen"
            @toggle-simulator="simulatorOpen = !simulatorOpen"
            @open-shift-modal="showShiftModal = true"
            @open-pin-modal="showPinModal = true"
        />

        <!-- Main Station Matrix Workspace -->
        <main class="max-w-7xl w-full mx-auto px-4 lg:px-6 py-6 flex-1 flex flex-col gap-6">
            <!-- Filter Bar & Lounge KPIs -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-surface-card/60 p-3 rounded-2xl border border-surface-border-subtle">
                <div class="flex items-center gap-2">
                    <button
                        @click="activeFilter = 'all'"
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer',
                            activeFilter === 'all'
                                ? 'bg-surface-elevated text-text-primary border border-surface-border'
                                : 'bg-surface-overlay text-text-muted hover:text-text-secondary border border-transparent'
                        ]"
                    >
                        All Stations ({{ stations.length }})
                    </button>
                    <button
                        @click="activeFilter = 'active'"
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer',
                            activeFilter === 'active'
                                ? 'bg-surface-elevated text-text-primary border border-surface-border'
                                : 'bg-surface-overlay text-text-muted hover:text-text-secondary border border-transparent'
                        ]"
                    >
                        Active In-Play ({{ activeStationsCount }})
                    </button>
                    <button
                        @click="activeFilter = 'available'"
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer',
                            activeFilter === 'available'
                                ? 'bg-surface-elevated text-text-primary border border-surface-border'
                                : 'bg-surface-overlay text-text-muted hover:text-text-secondary border border-transparent'
                        ]"
                    >
                        Available Ready ({{ stations.length - activeStationsCount }})
                    </button>
                    <button
                        v-if="rogueCount > 0"
                        @click="activeFilter = 'rogue'"
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5',
                            activeFilter === 'rogue'
                                ? 'bg-surface-elevated text-text-primary border border-surface-border'
                                : 'bg-surface-overlay text-status-rogue border border-status-rogue/40'
                        ]"
                    >
                        <TriangleAlert class="w-3.5 h-3.5" />
                        Rogue ({{ rogueCount }})
                    </button>
                </div>

                <div class="text-xs text-text-muted font-medium">
                    Currency: <span class="text-text-primary font-semibold font-mono">Libyan Dinar (LYD / د.ل)</span>
                </div>
            </div>

            <!-- Station Cards Responsive Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <StationCard
                    v-for="station in filteredStations"
                    :key="station.id"
                    :station="station"
                    @start-session="onStartSession"
                    @add-retail-item="onAddRetailItem"
                    @switch-tier="onSwitchTier"
                    @extend-time="onExtendTime"
                    @pause-session="onPauseSession"
                    @resume-session="onResumeSession"
                    @end-session="onEndSession"
                    @settle-payment="onSettlePayment"
                    @transfer-station="onTransferStation"
                    @force-sleep="onForceSleep"
                    @claim-rogue="onClaimRogue"
                />
            </div>
        </main>

        <!-- Modals -->
        <StartSessionModal
            :show="showStartModal"
            :station="selectedStation"
            :pricing-tiers="pricingTiers"
            :initial-backdate-minutes="initialBackdateMinutes"
            @close="showStartModal = false"
        />

        <AddRetailItemModal
            :show="showRetailModal"
            :station="selectedStation"
            :products="products"
            @close="showRetailModal = false"
        />

        <SwitchTierModal
            :show="showTierModal"
            :station="selectedStation"
            :pricing-tiers="pricingTiers"
            @close="showTierModal = false"
        />

        <CheckoutInvoiceModal
            :show="showCheckoutModal"
            :station="selectedStation"
            @close="showCheckoutModal = false"
        />

        <ShiftModal
            :show="showShiftModal"
            :active-shift="activeShift"
            @close="showShiftModal = false"
        />

        <PinSwitchModal
            :show="showPinModal"
            :staff-users="staffUsers"
            @close="showPinModal = false"
        />

        <TransferModal
            :show="showTransferModal"
            :station="selectedStation"
            :all-stations="stations"
            @close="showTransferModal = false"
        />

        <!-- Hardware Simulator Drawer -->
        <HardwareSimulatorDrawer
            :show="simulatorOpen"
            :stations="stations"
            @close="simulatorOpen = false"
        />
    </div>
</template>
