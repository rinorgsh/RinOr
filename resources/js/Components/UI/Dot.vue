<script setup>
defineProps({
    color: { type: String, default: null },
    size: { type: Number, default: 8 },
});
</script>

<template>
    <span
        class="inline-block shrink-0 rounded-full"
        :class="color ? '' : 'border border-dashed border-line-strong'"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            backgroundColor: color || 'transparent',
        }"
        aria-hidden="true"
    />
</template>
