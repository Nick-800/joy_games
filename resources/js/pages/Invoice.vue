<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer, Gamepad2 } from 'lucide-vue-next';

interface Props {
    session: {
        id: number;
        session_type: string;
        status: string;
        customer_name: string | null;
        customer_phone: string | null;
        started_at: string | null;
        ended_at: string | null;
        total_paused_seconds: number;
        cashier_name: string | null;
        station_name: string | null;
        payment_method: string | null;
        payment_status: string;
    };
    totals: {
        total_duration_seconds: number;
        total_billable_minutes: number;
        time_amount_millimes: number;
        time_amount_lyd: number;
        retail_amount_millimes: number;
        retail_amount_lyd: number;
        discount_amount_millimes: number;
        discount_amount_lyd: number;
        final_total_millimes: number;
        final_total_lyd: number;
    };
    intervals: Array<{
        id: number;
        pricing_tier: string;
        rate_per_hour_millimes: number;
        station_multiplier: number;
        started_at: string | null;
        ended_at: string | null;
        duration_seconds: number;
        billable_minutes: number;
        subtotal_millimes: number;
    }>;
    orderItems: Array<{
        id: number;
        item_name: string;
        quantity: number;
        unit_price_millimes: number;
        subtotal_millimes: number;
    }>;
}

defineProps<Props>();

function formatDate(iso: string | null): string {
    if (!iso) {
return '—';
}

    return new Date(iso).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="Invoice" />

    <div class="min-h-screen bg-surface-canvas text-text-primary p-6 print:p-0 print:bg-white print:text-black">
        <div class="max-w-2xl mx-auto bg-surface-elevated border border-surface-border rounded-2xl shadow-xl p-8 print:shadow-none print:border-0 print:bg-white">
            <!-- Action Bar (no-print) -->
            <div class="flex items-center justify-between mb-6 print:hidden">
                <a
                    href="/"
                    class="text-xs text-text-muted hover:text-text-primary flex items-center gap-1.5 transition"
                >
                    ← Back to Dashboard
                </a>
                <button
                    @click="printPage"
                    type="button"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-brand-primary hover:bg-brand-primary-hover text-text-primary font-semibold text-xs transition cursor-pointer"
                >
                    <Printer class="w-3.5 h-3.5" />
                    Print Invoice
                </button>
            </div>

            <!-- Header -->
            <div class="flex items-start justify-between border-b border-surface-border-subtle pb-4 mb-6 print:border-black">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-9 h-9 rounded-lg bg-brand-primary flex items-center justify-center print:bg-black">
                            <Gamepad2 class="w-5 h-5" />
                        </div>
                        <h1 class="text-xl font-bold tracking-tight">JOY GAMES</h1>
                    </div>
                    <p class="text-xs text-text-muted print:text-gray-600">PS5 Lounge · Tripoli, Libya</p>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-wider text-text-muted print:text-gray-600">Invoice</p>
                    <p class="text-lg font-semibold font-mono">#{{ session.id }}</p>
                    <p class="text-xs text-text-secondary print:text-gray-700">{{ formatDate(session.ended_at ?? session.started_at) }}</p>
                </div>
            </div>

            <!-- Meta grid -->
            <div class="grid grid-cols-2 gap-4 mb-6 text-xs">
                <div>
                    <p class="text-text-muted uppercase tracking-wider mb-1 print:text-gray-600">Customer</p>
                    <p class="font-semibold text-sm">{{ session.customer_name ?? 'Walk-in' }}</p>
                    <p v-if="session.customer_phone" class="text-text-secondary print:text-gray-700">{{ session.customer_phone }}</p>
                </div>
                <div>
                    <p class="text-text-muted uppercase tracking-wider mb-1 print:text-gray-600">Station / Cashier</p>
                    <p class="font-semibold text-sm">{{ session.station_name ?? '—' }}</p>
                    <p class="text-text-secondary print:text-gray-700">Served by {{ session.cashier_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-text-muted uppercase tracking-wider mb-1 print:text-gray-600">Billing</p>
                    <p class="font-semibold text-sm uppercase">{{ session.session_type }}</p>
                    <p class="text-text-secondary print:text-gray-700">Started: {{ formatDate(session.started_at) }}</p>
                </div>
                <div>
                    <p class="text-text-muted uppercase tracking-wider mb-1 print:text-gray-600">Payment</p>
                    <p class="font-semibold text-sm uppercase">{{ session.payment_status }}</p>
                    <p class="text-text-secondary print:text-gray-700">{{ session.payment_method ?? '—' }}</p>
                </div>
            </div>

            <!-- Time Intervals -->
            <div class="mb-6">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted mb-2 print:text-gray-600">Time Charges</h2>
                <div class="bg-surface-canvas rounded-lg border border-surface-border-subtle overflow-hidden print:border-gray-300 print:bg-white">
                    <table class="w-full text-xs">
                        <thead class="bg-surface-overlay text-text-muted print:bg-gray-100 print:text-gray-700">
                            <tr>
                                <th class="text-left px-3 py-2 font-semibold">Tier</th>
                                <th class="text-right px-3 py-2 font-semibold">Rate</th>
                                <th class="text-right px-3 py-2 font-semibold">Time</th>
                                <th class="text-right px-3 py-2 font-semibold">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-border-subtle print:divide-gray-200">
                            <tr v-for="inv in intervals" :key="inv.id">
                                <td class="px-3 py-2 font-semibold">{{ inv.pricing_tier }}</td>
                                <td class="px-3 py-2 text-right font-mono tabular-nums">
                                    {{ Math.round(inv.rate_per_hour_millimes / 1000) }} /hr
                                </td>
                                <td class="px-3 py-2 text-right font-mono tabular-nums">
                                    {{ inv.billable_minutes }}m
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">
                                    {{ Math.round(inv.subtotal_millimes / 1000) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

                    <!-- Totals -->
            <div class="border-t border-surface-border pt-4 print:border-black">
                <div class="flex flex-col gap-1.5 text-sm ml-auto max-w-xs">
                    <div class="flex justify-between text-text-secondary print:text-gray-700">
                        <span>Time subtotal:</span>
                        <span class="font-mono tabular-nums">{{ Math.round(totals.time_amount_lyd) }}</span>
                    </div>
                    <div v-if="totals.discount_amount_millimes > 0" class="flex justify-between text-text-secondary print:text-gray-700">
                        <span>Discount:</span>
                        <span class="font-mono tabular-nums">-{{ Math.round(totals.discount_amount_lyd) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 mt-1 border-t border-surface-border text-base font-bold print:border-black">
                        <span>Total Due</span>
                        <span class="text-xl font-mono tabular-nums">{{ Math.round(totals.final_total_lyd) }}</span>
                    </div>
                </div>
            </div>

            <p class="text-center text-xs text-text-muted mt-8 print:text-gray-500 print:mt-12">
                Thank you for gaming with us · Joy Games {{ new Date().getFullYear() }}
            </p>
        </div>
    </div>
</template>
