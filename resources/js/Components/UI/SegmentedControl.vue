<script setup>
defineProps({
    modelValue: { type: [String, Number, Boolean], default: null },
    /** [{ value, label, count? }] */
    options: { type: Array, required: true },
    ariaLabel: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div
        class="inline-flex w-full rounded-lg border border-line bg-surface-2 p-0.5 sm:w-auto"
        role="tablist"
        :aria-label="ariaLabel"
    >
        <button
            v-for="opt in options"
            :key="String(opt.value)"
            type="button"
            role="tab"
            :aria-selected="modelValue === opt.value"
            class="flex-1 rounded-[7px] px-3 py-1.5 text-xs font-medium transition whitespace-nowrap sm:flex-none"
            :class="
                modelValue === opt.value
                    ? 'bg-surface text-ink shadow-sm'
                    : 'text-ink-3 hover:text-ink-2'
            "
            @click="emit('update:modelValue', opt.value)"
        >
            {{ opt.label }}
            <span v-if="opt.count !== undefined" class="tnum ml-1 opacity-60">{{ opt.count }}</span>
        </button>
    </div>
</template>
