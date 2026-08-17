<script setup>
import { computed } from 'vue';
import { formatCents, formatSignedCents } from '@/Composables/useMoney';

const props = defineProps({
    cents: { type: [Number, String], default: 0 },
    /** 'none' = pas de couleur ; 'sign' = vert/rouge selon le signe ; 'in'/'out' = forcé. */
    tone: { type: String, default: 'none' },
    signed: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const n = computed(() => Number(props.cents) || 0);

const text = computed(() =>
    props.signed
        ? formatSignedCents(n.value, { compact: props.compact })
        : formatCents(n.value, { compact: props.compact }),
);

const toneClass = computed(() => {
    if (props.tone === 'in') return 'text-pos';
    if (props.tone === 'out') return 'text-neg';
    if (props.tone === 'sign') {
        if (n.value > 0) return 'text-pos';
        if (n.value < 0) return 'text-neg';
    }

    return '';
});
</script>

<template>
    <span class="tnum whitespace-nowrap" :class="toneClass">{{ text }}</span>
</template>
