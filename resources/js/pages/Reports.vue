<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Wallet,
    Clock,
    Calendar,
    Download,
    Filter,
    X,
    ChevronRight,
    Receipt,
    TrendingUp,
    Gamepad2,
    Timer,
    BarChart3,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';

interface StationOption {
    id: number;
    name: string;
    station_number: number;
}

interface CashierOption {
    id: number;
    name: string;
}

interface Bucket {
    key: string;
    label: string;
    starts_at: string;
    ends_at: string;
    time_lyd: number;
    discount_lyd: number;
    final_lyd: number;
    cash_lyd: number;
    sessions: number;
    minutes: number;
    prepaid_sessions: number;
    postpaid_sessions: number;
    vip_sessions: number;
    station_breakdown: Array<{
        station_id: number;
        station_name: string;
        time_lyd: number;
        discount_lyd: number;
        final_lyd: number;
        cash_lyd: number;
        sessions: number;
        minutes: number;
    }>;
}

interface ReportResponse {
    mode: 'daily' | 'weekly' | 'monthly';
    from: string;
    to: string;
    timezone: string;
    filters: {
        station_ids: number[];
        cashier_ids: number[];
        session_types: string[];
    };
    totals: {
        time_lyd: number;
        discount_lyd: number;
        final_lyd: number;
        cash_lyd: number;
        sessions: number;
        minutes: number;
        prepaid_sessions: number;
        postpaid_sessions: number;
        vip_sessions: number;
    };
    buckets: Bucket[];
}

const props = defineProps<{
    stations: StationOption[];
    cashiers: CashierOption[];
    serverTime: string;
    defaultRange: { from: string; to: string };
    rule: { timezone: string; currency_symbol: string };
}>();

const mode = ref<'daily' | 'weekly' | 'monthly'>('daily');
const from = ref<string>(props.defaultRange.from);
const to = ref<string>(props.defaultRange.to);
const stationIds = ref<number[]>([]);
const cashierIds = ref<number[]>([]);
const sessionTypes = ref<string[]>([]);
const showFilters = ref(true);
const showTheoretical = ref(false);

const report = ref<ReportResponse | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const selectedBucket = ref<Bucket | null>(null);
const bucketSessions = ref<Array<Record<string, unknown>>>([]);
const bucketSessionsLoading = ref(false);

async function fetchReport() {
    loading.value = true;
    error.value = null;

    try {
        const url = new URL('/reports/data', window.location.origin);
        url.searchParams.set('mode', mode.value);
        url.searchParams.set('from', from.value);
        url.searchParams.set('to', to.value);
        stationIds.value.forEach((id) => url.searchParams.append('station_ids[]', String(id)));
        cashierIds.value.forEach((id) => url.searchParams.append('cashier_ids[]', String(id)));
        sessionTypes.value.forEach((p) => url.searchParams.append('session_types[]', p));

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error ${response.status}`);
        }

        report.value = await response.json();
    } catch {
        error.value = 'Failed to load report data.';
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    const url = new URL('/reports/export', window.location.origin);
    url.searchParams.set('mode', mode.value);
    url.searchParams.set('from', from.value);
    url.searchParams.set('to', to.value);
    stationIds.value.forEach((id) => url.searchParams.append('station_ids[]', String(id)));
    cashierIds.value.forEach((id) => url.searchParams.append('cashier_ids[]', String(id)));
    sessionTypes.value.forEach((p) => url.searchParams.append('session_types[]', p));
    window.open(url.toString(), '_blank');
}

function applyQuickRange(days: number, weeks = 0, months = 0) {
    const toDate = new Date();
    const fromDate = new Date();

    if (months > 0) {
        fromDate.setMonth(fromDate.getMonth() - months);
    } else if (weeks > 0) {
        fromDate.setDate(fromDate.getDate() - (weeks * 7));
    } else {
        fromDate.setDate(fromDate.getDate() - days);
    }

    from.value = fromDate.toISOString().slice(0, 10);
    to.value = toDate.toISOString().slice(0, 10);
    fetchReport();
}

function clearFilters() {
    stationIds.value = [];
    cashierIds.value = [];
    sessionTypes.value = [];
    fetchReport();
}

function toggleArrayValue<T>(arr: T[], value: T): T[] {
    return arr.includes(value) ? arr.filter((v) => v !== value) : [...arr, value];
}

async function selectBucket(bucket: Bucket) {
    selectedBucket.value = bucket;
    bucketSessionsLoading.value = true;

    try {
        const url = new URL('/reports/sessions', window.location.origin);
        url.searchParams.set('mode', mode.value);
        url.searchParams.set('key', bucket.key);
        stationIds.value.forEach((id) => url.searchParams.append('station_ids[]', String(id)));
        cashierIds.value.forEach((id) => url.searchParams.append('cashier_ids[]', String(id)));
        sessionTypes.value.forEach((p) => url.searchParams.append('session_types[]', p));

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error ${response.status}`);
        }

        const data = await response.json();
        bucketSessions.value = data.sessions ?? [];
    } catch {
        bucketSessions.value = [];
    } finally {
        bucketSessionsLoading.value = false;
    }
}

function formatLyd(millimes: number): string {
    return Math.round(millimes / 1000).toString();
}

const maxBucketRevenue = computed(() => {
    if (!report.value) {
        return 0;
    }

    return Math.max(1, ...report.value.buckets.map((b) => b.final_lyd));
});

const avgTicket = computed(() => {
    if (!report.value || report.value.totals.sessions === 0) {
        return '0';
    }

    return formatLyd(Math.round(report.value.totals.final_lyd / report.value.totals.sessions));
});

const avgSessionDuration = computed(() => {
    if (!report.value || report.value.totals.sessions === 0) {
        return '0m';
    }

    const mins = Math.round(report.value.totals.minutes / report.value.totals.sessions);

    if (mins < 60) {
        return `${mins}m`;
    }

    const h = Math.floor(mins / 60);
    const rem = mins % 60;

    return rem > 0 ? `${h}h ${rem}m` : `${h}h`;
});

const totalPlaytimeFormatted = computed(() => {
    if (!report.value || report.value.totals.minutes === 0) {
        return '0h';
    }

    const mins = report.value.totals.minutes;
    const h = Math.floor(mins / 60);
    const rem = mins % 60;

    if (h === 0) {
        return `${rem}m`;
    }

    return rem > 0 ? `${h}h ${rem}m` : `${h}h`;
});

const hourlyYield = computed(() => {
    if (!report.value || report.value.totals.minutes === 0) {
        return '0';
    }

    const hours = report.value.totals.minutes / 60;

    return formatLyd(Math.round(report.value.totals.final_lyd / hours));
});

const prepaidSharePercent = computed(() => {
    if (!report.value || report.value.totals.sessions === 0) {
        return 0;
    }

    return Math.round((report.value.totals.prepaid_sessions / report.value.totals.sessions) * 100);
});

const postpaidSharePercent = computed(() => {
    if (!report.value || report.value.totals.sessions === 0) {
        return 0;
    }

    return Math.max(0, 100 - prepaidSharePercent.value);
});

const dailyAverageRevenue = computed(() => {
    if (!report.value || report.value.buckets.length === 0) {
        return '0';
    }

    return formatLyd(Math.round(report.value.totals.final_lyd / report.value.buckets.length));
});

const discountPercentage = computed(() => {
    if (!report.value) {
        return 0;
    }

    const gross = report.value.totals.final_lyd + report.value.totals.discount_lyd;

    if (gross === 0) {
        return 0;
    }

    return Math.round((report.value.totals.discount_lyd / gross) * 100);
});

watch([mode], () => fetchReport());

fetchReport();
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
                        ←
                    </Link>
                    <div class="w-10 h-10 rounded-lg bg-status-available flex items-center justify-center">
                        <Receipt class="w-6 h-6 text-surface-canvas" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold tracking-tight">Revenue Reports</h1>
                        <p class="text-xs text-text-muted">Daily · Weekly · Monthly aggregation across all stations</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="showTheoretical = !showTheoretical"
                        type="button"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer',
                            showTheoretical
                                ? 'bg-status-warning/20 text-status-warning border-status-warning/50'
                                : 'bg-surface-overlay text-text-muted border-surface-border-subtle'
                        ]"
                    >
                        {{ showTheoretical ? 'Showing Theoretical Revenue' : 'Showing Billed Revenue' }}
                    </button>
                    <button
                        @click="exportCsv"
                        type="button"
                        class="px-3 py-1.5 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <Download class="w-3.5 h-3.5" /> Export CSV
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-7xl w-full mx-auto px-4 lg:px-6 py-6 flex-1 flex flex-col gap-6">
            <!-- Mode Tabs -->
            <div class="flex items-center gap-2 border-b border-surface-border-subtle">
                <button
                    v-for="m in (['daily', 'weekly', 'monthly'] as const)"
                    :key="m"
                    @click="mode = m"
                    type="button"
                    :class="[
                        'px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px cursor-pointer',
                        mode === m
                            ? 'text-brand-primary border-brand-primary'
                            : 'text-text-muted border-transparent hover:text-text-secondary'
                    ]"
                >
                    {{ m.charAt(0).toUpperCase() + m.slice(1) }}
                </button>
            </div>

            <!-- Date Range & Quick Picker -->
            <div class="bg-surface-card border border-surface-border-subtle rounded-2xl p-4 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-text-muted" />
                    <label class="text-xs uppercase font-semibold text-text-muted">From</label>
                    <input
                        v-model="from"
                        type="date"
                        class="bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary text-xs font-mono px-3 py-1.5 focus:outline-none focus:border-brand-primary"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs uppercase font-semibold text-text-muted">To</label>
                    <input
                        v-model="to"
                        type="date"
                        class="bg-surface-canvas border border-surface-border-subtle rounded-lg text-text-primary text-xs font-mono px-3 py-1.5 focus:outline-none focus:border-brand-primary"
                    />
                </div>

                <div class="flex items-center gap-1">
                    <button
                        v-for="r in [
                            { label: '7d', fn: () => applyQuickRange(7) },
                            { label: '30d', fn: () => applyQuickRange(30) },
                            { label: '12w', fn: () => applyQuickRange(0, 12) },
                            { label: '6m', fn: () => applyQuickRange(0, 0, 6) },
                            { label: '12m', fn: () => applyQuickRange(0, 0, 12) },
                        ]"
                        :key="r.label"
                        @click="r.fn"
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-surface-overlay text-text-secondary hover:bg-surface-border transition cursor-pointer"
                    >
                        {{ r.label }}
                    </button>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <button
                        @click="showFilters = !showFilters"
                        type="button"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition border flex items-center gap-1.5 cursor-pointer',
                            showFilters
                                ? 'bg-brand-primary text-text-primary border-transparent'
                                : 'bg-surface-overlay text-text-muted border-surface-border-subtle'
                        ]"
                    >
                        <Filter class="w-3.5 h-3.5" /> Filters
                    </button>
                    <button
                        @click="fetchReport"
                        type="button"
                        :disabled="loading"
                        class="px-4 py-1.5 rounded-lg bg-status-available text-surface-canvas text-xs font-semibold transition disabled:opacity-50 cursor-pointer"
                    >
                        {{ loading ? 'Loading...' : 'Apply' }}
                    </button>
                </div>
            </div>

            <!-- Filters Panel -->
            <div
                v-if="showFilters"
                class="bg-surface-card border border-surface-border-subtle rounded-2xl p-4 grid grid-cols-1 md:grid-cols-4 gap-4"
            >
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">Stations</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="s in stations"
                            :key="s.id"
                            @click="stationIds = toggleArrayValue(stationIds, s.id)"
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition border cursor-pointer',
                                stationIds.includes(s.id)
                                    ? 'bg-brand-primary text-text-primary border-transparent'
                                    : 'bg-surface-overlay text-text-secondary border-surface-border-subtle'
                            ]"
                        >
                            {{ s.name }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">Cashiers</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="c in cashiers"
                            :key="c.id"
                            @click="cashierIds = toggleArrayValue(cashierIds, c.id)"
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition border cursor-pointer',
                                cashierIds.includes(c.id)
                                    ? 'bg-brand-primary text-text-primary border-transparent'
                                    : 'bg-surface-overlay text-text-secondary border-surface-border-subtle'
                            ]"
                        >
                            {{ c.name }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">Session Type</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="t in ['prepaid', 'postpaid']"
                            :key="t"
                            @click="sessionTypes = toggleArrayValue(sessionTypes, t)"
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition border cursor-pointer',
                                sessionTypes.includes(t)
                                    ? 'bg-status-prepaid text-text-primary border-transparent'
                                    : 'bg-surface-overlay text-text-secondary border-surface-border-subtle'
                            ]"
                        >
                            {{ t }}
                        </button>
                    </div>
                </div>

                <div class="md:col-span-3 flex justify-end">
                    <button
                        @click="clearFilters"
                        type="button"
                        class="px-3 py-1.5 rounded-lg bg-status-rogue hover:brightness-110 text-text-primary text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <X class="w-3.5 h-3.5" /> Clear Filters
                    </button>
                </div>
            </div>

            <!-- KPI Dashboard Section (shadcn components) -->
            <div v-if="report" class="flex flex-col gap-4">
                <!-- 1. Primary Metrics Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Net Revenue -->
                    <Card class="relative overflow-hidden group border-surface-border-subtle hover:border-status-available/40">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-status-available/5 rounded-full blur-xl pointer-events-none group-hover:bg-status-available/10 transition-colors"></div>
                        <CardHeader>
                            <CardTitle>
                                <span>Total Net Revenue</span>
                                <Badge variant="success" class="text-[10px] font-mono">
                                    {{ report.buckets.length }} {{ report.buckets.length === 1 ? 'day' : 'days' }}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold font-mono tracking-tight text-status-available tabular-nums">
                                {{ formatLyd(report.totals.final_lyd) }}
                                <span class="text-xs font-semibold text-text-muted ml-1">LYD</span>
                            </div>
                            <CardDescription>
                                Total billed customer revenue
                            </CardDescription>
                        </CardContent>
                        <CardFooter class="justify-between text-[11px] pt-3">
                            <span class="flex items-center gap-1.5 text-text-secondary">
                                <TrendingUp class="w-3.5 h-3.5 text-status-available" />
                                Avg {{ dailyAverageRevenue }} LYD/day
                            </span>
                        </CardFooter>
                    </Card>

                    <!-- Total Sessions -->
                    <Card class="relative overflow-hidden group border-surface-border-subtle hover:border-brand-primary/40">
                        <CardHeader>
                            <CardTitle>
                                <span>Sessions Played</span>
                                <div class="w-6 h-6 rounded-lg bg-brand-primary/10 flex items-center justify-center text-brand-primary">
                                    <Gamepad2 class="w-3.5 h-3.5" />
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold font-mono tracking-tight text-text-primary tabular-nums">
                                {{ report.totals.sessions }}
                            </div>
                            <CardDescription>
                                {{ totalPlaytimeFormatted }} total play duration
                            </CardDescription>
                        </CardContent>
                        <CardFooter class="justify-between text-[11px] pt-3">
                            <span class="text-text-secondary">
                                {{ report.totals.vip_sessions }} VIP sessions
                            </span>
                            <Badge variant="outline" class="text-[10px] font-mono">
                                {{ avgSessionDuration }} avg
                            </Badge>
                        </CardFooter>
                    </Card>

                    <!-- Average Ticket / ARPU -->
                    <Card class="relative overflow-hidden group border-surface-border-subtle hover:border-surface-border">
                        <CardHeader>
                            <CardTitle>
                                <span>Average Ticket</span>
                                <div class="w-6 h-6 rounded-lg bg-surface-elevated flex items-center justify-center text-text-secondary">
                                    <Receipt class="w-3.5 h-3.5" />
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold font-mono tracking-tight text-text-primary tabular-nums">
                                {{ avgTicket }}
                                <span class="text-xs font-semibold text-text-muted ml-1">LYD</span>
                            </div>
                            <CardDescription>
                                Average spend per visit
                            </CardDescription>
                        </CardContent>
                        <CardFooter class="justify-between text-[11px] pt-3">
                            <span class="flex items-center gap-1 text-text-secondary">
                                <Clock class="w-3.5 h-3.5 text-text-muted" />
                                {{ avgSessionDuration }} avg duration
                            </span>
                        </CardFooter>
                    </Card>

                    <!-- Hourly Gameplay Yield -->
                    <Card class="relative overflow-hidden group border-surface-border-subtle hover:border-brand-primary/40">
                        <CardHeader>
                            <CardTitle>
                                <span>Hourly Yield</span>
                                <div class="w-6 h-6 rounded-lg bg-brand-primary/10 flex items-center justify-center text-brand-primary">
                                    <Timer class="w-3.5 h-3.5" />
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold font-mono tracking-tight text-brand-primary tabular-nums">
                                {{ hourlyYield }}
                                <span class="text-xs font-semibold text-text-muted ml-1">LYD/hr</span>
                            </div>
                            <CardDescription>
                                Yield per console hour
                            </CardDescription>
                        </CardContent>
                        <CardFooter class="justify-between text-[11px] pt-3">
                            <span class="text-text-secondary">
                                Active station play rate
                            </span>
                        </CardFooter>
                    </Card>
                </div>

                <!-- 2. Analytical Deep-Dive Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- Session Distribution Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <span>Session Model Share</span>
                                <BarChart3 class="w-4 h-4 text-text-muted" />
                            </CardTitle>
                            <CardDescription>Prepaid vs postpaid customer usage</CardDescription>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3">
                            <!-- Dual Progress Bar -->
                            <div class="h-2 w-full bg-surface-overlay rounded-full overflow-hidden flex">
                                <div
                                    class="bg-status-prepaid h-full transition-all duration-300"
                                    :style="{ width: `${prepaidSharePercent}%` }"
                                    :title="`Prepaid: ${prepaidSharePercent}%`"
                                ></div>
                                <div
                                    class="bg-status-postpaid h-full transition-all duration-300"
                                    :style="{ width: `${postpaidSharePercent}%` }"
                                    :title="`Postpaid: ${postpaidSharePercent}%`"
                                ></div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div class="p-2.5 rounded-xl bg-surface-canvas border border-surface-border-subtle">
                                    <div class="flex items-center gap-1.5 text-xs text-text-muted">
                                        <span class="w-2 h-2 rounded-full bg-status-prepaid"></span>
                                        Prepaid
                                    </div>
                                    <div class="text-base font-bold font-mono tabular-nums text-text-primary mt-1">
                                        {{ report.totals.prepaid_sessions }} <span class="text-xs font-normal text-text-muted">sessions</span>
                                    </div>
                                    <span class="text-[10px] text-text-muted font-mono">{{ prepaidSharePercent }}% share</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-surface-canvas border border-surface-border-subtle">
                                    <div class="flex items-center gap-1.5 text-xs text-text-muted">
                                        <span class="w-2 h-2 rounded-full bg-status-postpaid"></span>
                                        Postpaid
                                    </div>
                                    <div class="text-base font-bold font-mono tabular-nums text-text-primary mt-1">
                                        {{ report.totals.postpaid_sessions }} <span class="text-xs font-normal text-text-muted">sessions</span>
                                    </div>
                                    <span class="text-[10px] text-text-muted font-mono">{{ postpaidSharePercent }}% share</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Financial Settlements & Discounts Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <span>Cash Flow & Discounts</span>
                                <Sparkles class="w-4 h-4 text-text-muted" />
                            </CardTitle>
                            <CardDescription>Cash collection and promotional relief</CardDescription>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-2.5 rounded-xl bg-surface-canvas border border-surface-border-subtle">
                                    <div class="flex items-center gap-1.5 text-xs text-text-muted">
                                        <Wallet class="w-3.5 h-3.5 text-status-available" />
                                        Cash Collected
                                    </div>
                                    <div class="text-base font-bold font-mono tabular-nums text-status-available mt-1">
                                        {{ formatLyd(report.totals.cash_lyd) }} <span class="text-xs font-normal text-text-muted">LYD</span>
                                    </div>
                                    <span class="text-[10px] text-text-muted font-mono">100% Cash Intake</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-surface-canvas border border-surface-border-subtle">
                                    <div class="flex items-center gap-1.5 text-xs text-text-muted">
                                        <span class="w-2 h-2 rounded-full bg-status-warning"></span>
                                        Discounts Waived
                                    </div>
                                    <div class="text-base font-bold font-mono tabular-nums text-text-primary mt-1">
                                        {{ formatLyd(report.totals.discount_lyd) }} <span class="text-xs font-normal text-text-muted">LYD</span>
                                    </div>
                                    <span class="text-[10px] text-text-muted font-mono">{{ discountPercentage }}% effective discount</span>
                                </div>
                            </div>
                            <div class="p-2 rounded-xl bg-surface-overlay/60 border border-surface-border-subtle/60 flex items-center justify-between text-xs text-text-secondary">
                                <span class="text-text-muted">Total Play Duration</span>
                                <span class="font-mono font-semibold text-text-primary">{{ totalPlaytimeFormatted }} ({{ report.totals.minutes }} mins)</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Bucket Table -->
            <div v-if="report" class="bg-surface-card border border-surface-border-subtle rounded-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-border-subtle flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-text-primary">Revenue Buckets</h3>
                    <span class="text-xs text-text-muted">{{ report.buckets.length }} {{ report.buckets.length === 1 ? 'day' : 'days' }} with revenue · tz {{ report.timezone }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-surface-overlay text-text-muted">
                            <tr>
                                <th class="text-left px-4 py-2.5 font-semibold">Bucket</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Sessions</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Time LYD</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Discount LYD</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Cash LYD</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Minutes</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Final LYD</th>
                                <th class="text-left px-4 py-2.5 font-semibold">Per-Station Breakdown</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Bar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-border-subtle">
                            <tr v-if="report.buckets.length === 0">
                                <td colspan="9" class="px-4 py-8 text-center text-text-muted">
                                    No revenue recorded for the selected period.
                                </td>
                            </tr>
                            <tr
                                v-for="bucket in report.buckets"
                                :key="bucket.key"
                                @click="selectBucket(bucket)"
                                class="hover:bg-surface-overlay/40 cursor-pointer transition"
                            >
                                <td class="px-4 py-2.5 font-semibold text-text-primary whitespace-nowrap">{{ bucket.label }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ bucket.sessions }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums text-text-secondary">{{ formatLyd(bucket.time_lyd) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums text-text-muted">{{ formatLyd(bucket.discount_lyd) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums text-text-secondary">{{ formatLyd(bucket.cash_lyd) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ bucket.minutes }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums font-bold text-status-available">{{ formatLyd(bucket.final_lyd) }}</td>
                                <td class="px-4 py-2.5 text-xs text-text-secondary max-w-xs">
                                    <div v-if="bucket.station_breakdown.length" class="flex flex-wrap gap-x-2 gap-y-1">
                                        <span
                                            v-for="sb in bucket.station_breakdown"
                                            :key="sb.station_id"
                                            class="font-mono"
                                        >
                                            <span class="text-text-muted">{{ sb.station_name }}:</span>
                                            <span class="font-semibold text-text-primary ml-0.5">{{ formatLyd(sb.final_lyd) }}</span>
                                        </span>
                                    </div>
                                    <span v-else class="text-text-muted">—</span>
                                </td>
                                <td class="px-4 py-2.5 w-32">
                                    <div class="h-2 bg-surface-overlay rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-status-available transition-all"
                                            :style="{ width: `${Math.max(2, (bucket.final_lyd / maxBucketRevenue) * 100)}%` }"
                                        ></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="report.buckets.length" class="bg-surface-overlay/60 text-text-secondary font-semibold">
                            <tr>
                                <td class="px-4 py-3 uppercase tracking-wider text-text-muted">Totals</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ report.totals.sessions }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ formatLyd(report.totals.time_lyd) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ formatLyd(report.totals.discount_lyd) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ formatLyd(report.totals.cash_lyd) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ report.totals.minutes }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums text-status-available">{{ formatLyd(report.totals.final_lyd) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div v-if="error" class="p-4 rounded-xl bg-status-rogue/10 border border-status-rogue/30 text-status-rogue text-sm">
                {{ error }}
            </div>
        </main>

        <!-- Drill-down Side Drawer -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="selectedBucket"
                class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm"
                @click="selectedBucket = null"
            ></div>
        </Transition>
        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="selectedBucket"
                class="fixed right-0 top-0 bottom-0 z-50 w-full max-w-md bg-surface-elevated border-l border-surface-border shadow-2xl overflow-y-auto"
            >
                <div class="px-5 py-4 border-b border-surface-border-subtle flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase font-semibold tracking-wider text-text-muted">{{ mode }} bucket</p>
                        <h3 class="text-base font-semibold text-text-primary">{{ selectedBucket.label }}</h3>
                    </div>
                    <button
                        @click="selectedBucket = null"
                        type="button"
                        class="p-1.5 rounded-lg text-text-muted hover:text-text-primary transition cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="px-5 py-4 flex flex-col gap-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-surface-overlay border border-surface-border-subtle">
                            <span class="text-text-muted block">Sessions</span>
                            <span class="text-lg font-semibold font-mono tabular-nums">{{ selectedBucket.sessions }}</span>
                        </div>
                        <div class="p-3 rounded-xl bg-surface-overlay border border-surface-border-subtle">
                            <span class="text-text-muted block">Final</span>
                            <span class="text-lg font-semibold font-mono tabular-nums text-status-available">{{ formatLyd(selectedBucket.final_lyd) }}</span>
                        </div>
                    </div>

                    <h4 class="text-xs font-semibold uppercase tracking-wider text-text-muted mt-2">Contributing Sessions</h4>

                    <div v-if="bucketSessionsLoading" class="text-text-muted text-xs">Loading sessions...</div>
                    <div v-else-if="!bucketSessions.length" class="text-text-muted text-xs">No sessions in this bucket.</div>
                    <div v-else class="flex flex-col gap-2">
                        <a
                            v-for="s in bucketSessions"
                            :key="String(s.id)"
                            :href="`/sessions/${s.id}/invoice`"
                            class="block p-3 rounded-xl bg-surface-canvas border border-surface-border-subtle hover:border-surface-border transition"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-semibold text-text-primary">#{{ s.id }} · {{ s.station_name }}</span>
                                    <span class="block text-text-muted text-xs mt-0.5">Cashier: {{ s.cashier_name }} · {{ String(s.payment_method ?? '—').toUpperCase() }}</span>
                                </div>
                                <ChevronRight class="w-4 h-4 text-text-muted" />
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs">
                                <span class="text-text-muted font-mono">{{ String(s.started_at ?? '') }}</span>
                                <span class="font-semibold font-mono tabular-nums text-status-available">{{ formatLyd(Number(s.final_total_millimes ?? 0)) }}</span>
                            </div>
                        </a>
                    </div>
                </div>
            </aside>
        </Transition>
    </div>
</template>
