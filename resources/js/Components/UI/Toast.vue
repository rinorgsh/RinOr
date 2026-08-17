<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';

const page = usePage();
const message = ref(null);
let timer = null;

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;

        message.value = flash;
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), 3200);
    },
    { immediate: true },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="message"
            class="animate-rise fixed inset-x-4 bottom-24 z-[60] mx-auto flex max-w-sm items-center gap-2.5
                   rounded-xl border border-line bg-surface px-4 py-3 shadow-xl
                   lg:inset-x-auto lg:bottom-6 lg:left-1/2 lg:-translate-x-1/2"
            role="status"
            aria-live="polite"
        >
            <Check class="size-4 shrink-0 text-pos" />
            <p class="text-sm text-ink">{{ message }}</p>
        </div>
    </Teleport>
</template>
