<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'ghost' }, // solid | outline | ghost | danger
    size: { type: String, default: 'md' },       // sm | md | lg
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const base =
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition ' +
    'disabled:opacity-40 disabled:pointer-events-none select-none';

const variants = {
    // L'accent est le contraste d'encre, pas une teinte : la couleur reste
    // réservée au sens (argent, statut, séries).
    solid: 'bg-ink text-page hover:opacity-85 active:opacity-75',
    outline: 'border border-line-strong text-ink hover:bg-surface-2',
    ghost: 'text-ink-2 hover:bg-surface-2 hover:text-ink',
    danger: 'text-neg hover:bg-neg-soft',
};

const sizes = {
    sm: 'h-8 px-2.5 text-xs',
    md: 'h-10 px-3.5 text-sm',
    lg: 'h-12 px-5 text-sm',
};

const classes = computed(() => [
    base,
    variants[props.variant] ?? variants.ghost,
    sizes[props.size] ?? sizes.md,
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <slot />
    </Link>
    <button v-else :type="type" :disabled="disabled" :class="classes">
        <slot />
    </button>
</template>
