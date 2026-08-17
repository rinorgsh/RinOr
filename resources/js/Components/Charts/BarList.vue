<script setup>
import { computed } from 'vue';
import Money from '../UI/Money.vue';
import Dot from '../UI/Dot.vue';

/**
 * Barres horizontales pour une magnitude à série unique (« où partent mes
 * dépenses », « ce qui rapporte le plus »).
 *
 * Série unique = UNE seule couleur pour toutes les barres. Colorer chaque barre
 * selon sa valeur double l'encodage de la longueur et brûle le seul canal
 * libre ; l'identité est portée par le libellé, pas par la teinte.
 *
 * La valeur exacte est écrite à côté de chaque barre : c'est à la fois la
 * lisibilité au premier coup d'œil et la « vue tableau » d'accessibilité, donc
 * rien ne dépend de la couleur seule.
 */
const props = defineProps({
    /** [{ name, total_cents, share?, color?, entries? }] */
    items: { type: Array, required: true },
    /** Couleur de la série (un seul hex pour toutes les barres). */
    color: { type: String, required: true },
    /** Affiche la pastille de couleur de catégorie devant le libellé. */
    showDots: { type: Boolean, default: false },
    max: { type: Number, default: 6 },
});

const rows = computed(() => props.items.slice(0, props.max));

// L'échelle part de la plus grosse valeur affichée : la barre la plus longue
// remplit la largeur, les autres se comparent à elle.
const peak = computed(() => Math.max(...rows.value.map((r) => r.total_cents), 1));

const width = (cents) => `${Math.max((cents / peak.value) * 100, 1.5)}%`;
</script>

<template>
    <ul class="space-y-3">
        <li v-for="row in rows" :key="row.name" class="group">
            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <span class="flex min-w-0 items-center gap-2">
                    <Dot v-if="showDots" :color="row.color" />
                    <span class="truncate text-sm text-ink">{{ row.name }}</span>
                    <span
                        v-if="row.entries && row.entries > 1"
                        class="tnum shrink-0 rounded bg-surface-2 px-1.5 py-0.5 text-[10px] text-ink-3"
                    >×{{ row.entries }}</span>
                </span>
                <span class="flex shrink-0 items-baseline gap-2">
                    <!-- Pourcentage entier : une décimale n'apporte rien ici et
                         impose un séparateur décimal à localiser. -->
                    <span v-if="row.share !== undefined" class="tnum text-xs text-ink-3">
                        {{ Math.round(row.share) }}&nbsp;%
                    </span>
                    <Money :cents="row.total_cents" class="text-sm font-medium" />
                </span>
            </div>

            <!-- Piste + barre : extrémité arrondie 4px, ancrée à la ligne de base -->
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-3">
                <div
                    class="h-full rounded-full transition-[width] duration-500 ease-out"
                    :style="{ width: width(row.total_cents), backgroundColor: color }"
                />
            </div>
        </li>
    </ul>
</template>
