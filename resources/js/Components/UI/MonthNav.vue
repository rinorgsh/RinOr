<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

const props = defineProps({
    /** { iso, label, previous, next, is_current } */
    month: { type: Object, required: true },
    /** Chemin de la page courante, ex. '/depenses' */
    basePath: { type: String, required: true },
});

const url = (iso) => `${props.basePath}?month=${iso}`;
const label = computed(() => {
    const l = props.month.label ?? '';

    return l.charAt(0).toUpperCase() + l.slice(1);
});
</script>

<template>
    <div class="flex items-center gap-1">
        <Link
            :href="url(month.previous)"
            preserve-scroll
            class="rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
            aria-label="Mois précédent"
        >
            <ChevronLeft class="size-4" />
        </Link>

        <div class="min-w-[8.5rem] text-center">
            <span class="text-sm font-medium text-ink">{{ label }}</span>
        </div>

        <Link
            :href="url(month.next)"
            preserve-scroll
            class="rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
            aria-label="Mois suivant"
        >
            <ChevronRight class="size-4" />
        </Link>

        <Link
            v-if="!month.is_current"
            :href="basePath"
            preserve-scroll
            class="ml-1 rounded-lg px-2.5 py-1.5 text-xs text-ink-3 transition hover:bg-surface-2 hover:text-ink"
        >
            Aujourd'hui
        </Link>
    </div>
</template>
