<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { X, Plus, Trash2, Coffee, ShoppingBag, Check } from 'lucide-vue-next';
import type { StationData } from '../StationCard.vue';

interface ProductItem {
    id: number;
    name: string;
    category: string;
    price_millimes: number;
    price_lyd: number;
    stock_quantity: number;
}

const props = defineProps<{
    show: boolean;
    station: StationData | null;
    products: ProductItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const selectedCategory = ref('all');

const categories = computed(() => {
    const cats = ['all', ...new Set(props.products.map(p => p.category))];
    return cats;
});

const filteredProducts = computed(() => {
    if (selectedCategory.value === 'all') return props.products;
    return props.products.filter(p => p.category === selectedCategory.value);
});

function addItem(product: ProductItem, qty: number = 1) {
    if (!props.station?.active_session) return;

    router.post('/pos/items', {
        product_id: product.id,
        game_session_id: props.station.active_session.id,
        quantity: qty,
    }, {
        preserveScroll: true,
    });
}

function removeItem(orderItemId: number) {
    router.delete(`/pos/items/${orderItemId}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div v-if="show && station" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-2xl bg-surface-elevated border border-surface-border rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-surface-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Coffee class="w-5 h-5 text-text-muted" />
                    <h3 class="text-base font-semibold text-text-primary">
                        Add Snacks & Drinks — {{ station.name }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-text-muted hover:text-text-primary transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
                <!-- Category Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        @click="selectedCategory = cat"
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-semibold uppercase transition cursor-pointer border',
                            selectedCategory === cat
                                ? 'bg-surface-border text-text-primary border-surface-border'
                                : 'bg-surface-overlay text-text-muted hover:text-text-secondary border-transparent'
                        ]"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Products Grid -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-text-muted mb-3">
                        Select Retail Product
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div
                            v-for="prod in filteredProducts"
                            :key="prod.id"
                            class="p-3 rounded-lg bg-surface-elevated hover:bg-surface-border active:bg-surface-border border border-surface-border-subtle flex flex-col justify-between gap-3 transition"
                        >
                            <div>
                                <span class="text-xs text-text-muted uppercase font-semibold">{{ prod.category }}</span>
                                <h5 class="text-sm font-semibold text-text-primary leading-tight mt-0.5">{{ prod.name }}</h5>
                                <p class="text-xs text-text-muted mt-1 font-mono tabular-nums">Stock: {{ prod.stock_quantity }}</p>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-surface-border-subtle">
                                <span class="text-sm font-semibold text-text-primary font-mono tabular-nums">
                                    {{ Math.round(prod.price_lyd) }} LYD
                                </span>
                                <button
                                    @click="addItem(prod, 1)"
                                    type="button"
                                    class="p-1.5 rounded-lg bg-surface-overlay hover:bg-surface-border text-text-secondary border border-surface-border-subtle transition cursor-pointer"
                                    title="Add 1 to Session"
                                >
                                    <Plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Session Retail Items List -->
                <div v-if="station.active_session?.order_items?.length">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-text-muted mb-2 flex items-center justify-between">
                        <span>Items on Tab:</span>
                        <span class="text-text-secondary font-mono tabular-nums">
                            Subtotal: {{ Math.round(station.active_session.retail_amount_lyd) }} LYD
                        </span>
                    </h4>
                    <div class="bg-surface-overlay rounded-xl border border-surface-border-subtle divide-y divide-surface-border-subtle overflow-hidden">
                        <div
                            v-for="item in station.active_session.order_items"
                            :key="item.id"
                            class="px-4 py-2.5 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-text-secondary font-mono tabular-nums">{{ item.quantity }}x</span>
                                <span class="text-text-primary font-medium">{{ item.item_name }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-text-primary font-semibold tabular-nums">
                                    {{ Math.round(item.subtotal_lyd) }} LYD
                                </span>
                                <button
                                    @click="removeItem(item.id)"
                                    type="button"
                                    class="p-1 text-text-muted hover:text-status-rogue transition cursor-pointer"
                                    title="Remove from tab"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-3 border-t border-surface-border-subtle flex justify-end">
                <button
                    @click="emit('close')"
                    type="button"
                    class="py-2 px-4 rounded-lg bg-surface-elevated hover:bg-surface-border text-text-secondary border border-surface-border-subtle font-semibold text-xs transition cursor-pointer"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
