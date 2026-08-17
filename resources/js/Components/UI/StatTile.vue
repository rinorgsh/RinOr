<script setup>
import Money from './Money.vue';

defineProps({
    label: { type: String, required: true },
    cents: { type: [Number, String], default: 0 },
    tone: { type: String, default: 'none' },
    signed: { type: Boolean, default: false },
    hint: { type: String, default: null },
    /** Barre de couleur à gauche : rappelle la série du graphique correspondant. */
    accent: { type: String, default: null },
});
</script>

<template>
    <div class="relative overflow-hidden rounded-xl border border-line bg-surface p-4">
        <span
            v-if="accent"
            class="absolute inset-y-0 left-0 w-[3px]"
            :style="{ backgroundColor: accent }"
            aria-hidden="true"
        />
        <p class="text-[11px] font-medium tracking-wide text-ink-3 uppercase">{{ label }}</p>
        <p class="mt-1.5 text-2xl leading-none sm:text-[1.75rem]">
            <Money :cents="cents" :tone="tone" :signed="signed" />
        </p>
        <p v-if="hint" class="mt-1.5 text-xs text-ink-3">{{ hint }}</p>
    </div>
</template>
