<script setup>
import { computed, ref } from 'vue';
import Money from '../UI/Money.vue';

/**
 * Rentrées vs sorties sur 6 mois — colonnes groupées, DEUX séries, UN SEUL axe.
 *
 * Deux échelles y sur un même graphique inventent une corrélation qui n'est pas
 * dans les données : les deux séries sont ici dans la même unité (des euros) et
 * partagent donc le même axe.
 *
 * Deux séries ⇒ légende toujours présente. Les valeurs ne sont pas écrites sur
 * chaque colonne (illisible) : elles apparaissent au survol côté desktop, au
 * tap côté mobile, et le maximum de l'axe est affiché en repère.
 */
const props = defineProps({
    /** [{ iso, label, income_cents, outflow_cents }] */
    trend: { type: Array, required: true },
    colorIn: { type: String, required: true },
    colorOut: { type: String, required: true },
});

const active = ref(null);

const peak = computed(() =>
    Math.max(...props.trend.flatMap((m) => [m.income_cents, m.outflow_cents]), 1),
);

/** Plafond d'axe arrondi vers le haut, pour un repère lisible. */
const axisMax = computed(() => {
    const euros = peak.value / 100;
    const magnitude = 10 ** Math.floor(Math.log10(Math.max(euros, 1)));
    const step = magnitude / 2;

    return Math.ceil(euros / step) * step * 100;
});

const height = (cents) => `${Math.max((cents / axisMax.value) * 100, cents > 0 ? 2 : 0)}%`;

const isActive = (iso) => active.value === iso;
</script>

<template>
    <div>
        <!-- Légende : l'identité des séries ne repose jamais sur la couleur seule -->
        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="flex items-center gap-1.5 text-xs text-ink-2">
                <span class="size-2 rounded-full" :style="{ backgroundColor: colorIn }" />
                Rentrées
            </span>
            <span class="flex items-center gap-1.5 text-xs text-ink-2">
                <span class="size-2 rounded-full" :style="{ backgroundColor: colorOut }" />
                Sorties
            </span>
            <span class="tnum ml-auto text-[10px] text-ink-3">
                max <Money :cents="axisMax" compact />
            </span>
        </div>

        <div class="relative">
            <!-- Grille en filets solides, un ton au-dessus de la surface -->
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40" aria-hidden="true">
                <div v-for="n in [0, 1, 2]" :key="n"
                     class="absolute inset-x-0 border-t border-line"
                     :style="{ top: `${(n / 2) * 100}%` }" />
            </div>

            <div class="relative flex h-40 items-end gap-1.5 sm:gap-3">
                <div
                    v-for="m in trend"
                    :key="m.iso"
                    class="group relative flex h-full flex-1 cursor-default items-end justify-center gap-[2px] sm:gap-1"
                    @pointerenter="active = m.iso"
                    @pointerleave="active = null"
                    @click="active = isActive(m.iso) ? null : m.iso"
                >
                    <!-- Écart de 2px entre colonnes adjacentes : la surface sépare, pas une bordure -->
                    <div
                        class="w-full max-w-8 rounded-t-[4px] transition-opacity"
                        :style="{
                            height: height(m.income_cents),
                            backgroundColor: colorIn,
                            opacity: active && !isActive(m.iso) ? 0.35 : 1,
                        }"
                    />
                    <div
                        class="w-full max-w-8 rounded-t-[4px] transition-opacity"
                        :style="{
                            height: height(m.outflow_cents),
                            backgroundColor: colorOut,
                            opacity: active && !isActive(m.iso) ? 0.35 : 1,
                        }"
                    />

                    <!-- Infobulle -->
                    <div
                        v-if="isActive(m.iso)"
                        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 w-max -translate-x-1/2
                               rounded-lg border border-line bg-surface px-2.5 py-2 shadow-lg"
                    >
                        <p class="mb-1 text-[10px] font-medium tracking-wide text-ink-3 uppercase">
                            {{ m.label }}
                        </p>
                        <p class="flex items-center gap-1.5 text-xs">
                            <span class="size-1.5 rounded-full" :style="{ backgroundColor: colorIn }" />
                            <Money :cents="m.income_cents" />
                        </p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-xs">
                            <span class="size-1.5 rounded-full" :style="{ backgroundColor: colorOut }" />
                            <Money :cents="m.outflow_cents" />
                        </p>
                    </div>
                </div>
            </div>

            <!-- Ligne de base, puis la bande d'axe : la hauteur du bloc l'inclut -->
            <div class="border-t border-line-strong" />
            <div class="flex gap-1.5 pt-2 sm:gap-3">
                <span
                    v-for="m in trend"
                    :key="m.iso"
                    class="flex-1 text-center text-[10px] transition-colors"
                    :class="isActive(m.iso) ? 'text-ink' : 'text-ink-3'"
                >{{ m.label }}</span>
            </div>
        </div>
    </div>
</template>
