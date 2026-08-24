<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X, ArrowRightLeft, CheckCircle2 } from 'lucide-vue-next';
import type { StationData } from '../StationCard.vue';

const props = defineProps<{
    show: boolean;
    station: StationData | null;
    allStations: StationData[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const availableDestinations = computed(() => {
    return props.allStations.filter(s => s.id !== props.station?.id && s.current_state === 'available');
});

import { computed } from 'vue';

function transfer(destId: number) {
    if (!props.station?.active_session) return;

    const form = useForm({
        destination_station_id: destId,
    });

    form.post(`/sessions/${props.station.active_session.id}/transfer`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}
</script>

<template>
    <div v-if="show && station" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-md bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-4 bg-[#1e293b]/70 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <ArrowRightLeft class="w-5 h-5 text-sky-400" />
                    <h3 class="text-lg font-bold text-white">
                        Transfer Session from {{ station.name }}
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

            <div class="p-6 flex flex-col gap-4">
                <p class="text-xs text-slate-300">
                    Select an available station to move this session to. The source console will be placed into Rest Mode and the destination console will wake up immediately.
                </p>

                <div v-if="availableDestinations.length === 0" class="p-4 rounded-xl bg-slate-900 text-center text-xs text-slate-400">
                    No other stations are currently available.
                </div>

                <div v-else class="space-y-2 max-h-60 overflow-y-auto">
                    <button
                        v-for="dest in availableDestinations"
                        :key="dest.id"
                        @click="transfer(dest.id)"
                        type="button"
                        class="w-full p-3 rounded-xl bg-[#090d16] hover:bg-slate-900 border border-slate-800 hover:border-sky-500/50 flex items-center justify-between transition cursor-pointer"
                    >
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                            <span class="text-sm font-bold text-white">{{ dest.name }}</span>
                            <span v-if="dest.is_vip" class="text-[10px] uppercase font-bold text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded border border-amber-400/20">VIP</span>
                        </div>
                        <span class="text-xs font-bold text-sky-400">
                            Move Here →
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
