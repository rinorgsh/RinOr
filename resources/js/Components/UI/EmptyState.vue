<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="relative overflow-hidden rounded-xl border border-dashed border-line px-6 py-10 text-center">
        <div class="grain pointer-events-none absolute inset-0" aria-hidden="true" />
        <div class="relative">
            <p class="font-display text-lg text-ink">{{ title }}</p>
            <p v-if="description" class="mx-auto mt-1.5 max-w-sm text-sm text-ink-3">
                {{ description }}
            </p>
            <div v-if="$slots.default" class="mt-4 flex justify-center">
                <slot />
            </div>
        </div>
    </div>
</template>
