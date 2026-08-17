<script setup>
defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    flush: { type: Boolean, default: false },
});
</script>

<template>
    <section class="rounded-xl border border-line bg-surface">
        <header
            v-if="title || $slots.header || $slots.action"
            class="flex items-start justify-between gap-3 px-4 pt-4 pb-3 sm:px-5"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h2 class="truncate text-base text-ink sm:text-lg">{{ title }}</h2>
                    <p v-if="subtitle" class="mt-0.5 text-xs text-ink-3">{{ subtitle }}</p>
                </slot>
            </div>
            <div v-if="$slots.action" class="shrink-0">
                <slot name="action" />
            </div>
        </header>

        <div :class="flush ? '' : 'px-4 pb-4 sm:px-5 sm:pb-5'">
            <slot />
        </div>
    </section>
</template>
