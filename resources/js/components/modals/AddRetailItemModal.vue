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
        <div class="w-full max-w-2xl bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-6 py-4 bg-[#1e293b]/70 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Coffee class="w-5 h-5 text-purple-400" />
                    <h3 class="text-lg font-bold text-white">
                        Add Snacks & Drinks — {{ station.name }}
                    </h3>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
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
                            'px-3.5 py-1.5 rounded-lg text-xs font-bold uppercase transition cursor-pointer',
                            selectedCategory === cat
                                ? 'bg-purple-600 text-white shadow-md'
                                : 'bg-slate-800/80 text-slate-400 hover:text-white hover:bg-slate-700'
                        ]"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Products Grid -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                        Select Retail Product
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div
                            v-for="prod in filteredProducts"
                            :key="prod.id"
                            class="p-3 rounded-xl bg-[#090d16] border border-slate-800 flex flex-col justify-between gap-3 hover:border-purple-500/50 transition group"
                        >
                            <div>
                                <span class="text-xs text-purple-400 uppercase font-bold">{{ prod.category }}</span>
                                <h5 class="text-sm font-bold text-white leading-tight mt-0.5">{{ prod.name }}</h5>
                                <p class="text-xs text-slate-400 mt-1 font-mono">Stock: {{ prod.stock_quantity }}</p>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-800">
                                <span class="text-sm font-bold text-sky-400 font-mono">
                                    {{ prod.price_lyd.toFixed(3) }} LYD
                                </span>
                                <button
                                    @click="addItem(prod, 1)"
                                    type="button"
                                    class="p-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white shadow transition cursor-pointer"
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
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center justify-between">
                        <span>Items on Tab:</span>
                        <span class="text-purple-300 font-mono">
                            Subtotal: {{ station.active_session.retail_amount_lyd.toFixed(3) }} LYD
                        </span>
                    </h4>
                    <div class="bg-[#090d16] rounded-xl border border-slate-800 divide-y divide-slate-800/60 overflow-hidden">
                        <div
                            v-for="item in station.active_session.order_items"
                            :key="item.id"
                            class="px-4 py-2.5 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-purple-400 font-mono">{{ item.quantity }}x</span>
                                <span class="text-white font-medium">{{ item.item_name }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-slate-300 font-bold">
                                    {{ item.subtotal_lyd.toFixed(3) }} LYD
                                </span>
                                <button
                                    @click="removeItem(item.id)"
                                    type="button"
                                    class="p-1 text-slate-500 hover:text-rose-400 transition cursor-pointer"
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
            <div class="px-6 py-3 bg-[#1e293b]/70 border-t border-slate-800 flex justify-end">
                <button
                    @click="emit('close')"
                    type="button"
                    class="py-2 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition cursor-pointer"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
