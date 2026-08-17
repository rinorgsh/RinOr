<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { X } from '@lucide/vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: null },
});

const emit = defineEmits(['close']);

const panel = ref(null);
const titleId = `modal-title-${Math.random().toString(36).slice(2, 9)}`;

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.stopPropagation();
        emit('close');
        return;
    }

    // Piège de focus : Tab reste à l'intérieur du panneau.
    if (event.key !== 'Tab' || !panel.value) return;

    const focusable = panel.value.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

let restoreFocusTo = null;

watch(
    () => props.open,
    async (open) => {
        if (open) {
            restoreFocusTo = document.activeElement;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', onKeydown);

            await nextTick();
            // Premier champ du formulaire, sinon le panneau lui-même.
            const target = panel.value?.querySelector(
                'input:not([type="hidden"]), select, textarea',
            );
            (target ?? panel.value)?.focus();
        } else {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', onKeydown);
            restoreFocusTo?.focus?.();
            restoreFocusTo = null;
        }
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
        >
            <div
                class="animate-fade absolute inset-0 bg-black/45 backdrop-blur-[2px]"
                @click="emit('close')"
            />

            <!--
                Mobile : feuille qui monte depuis le bas, dans la zone du pouce.
                Desktop : dialogue centré.
            -->
            <div
                ref="panel"
                tabindex="-1"
                class="animate-sheet relative flex max-h-[92dvh] w-full flex-col overflow-hidden
                       rounded-t-2xl border border-line bg-surface shadow-2xl outline-none
                       sm:animate-rise sm:max-w-lg sm:rounded-2xl"
            >
                <header class="hairline-b flex items-start justify-between gap-3 px-5 pt-5 pb-4">
                    <div class="min-w-0">
                        <h2 :id="titleId" class="text-lg text-ink">{{ title }}</h2>
                        <p v-if="description" class="mt-1 text-xs text-ink-3">{{ description }}</p>
                    </div>
                    <button
                        type="button"
                        class="-mt-1 -mr-1 shrink-0 rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
                        aria-label="Fermer"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                    <slot />
                </div>

                <footer
                    v-if="$slots.footer"
                    class="hairline-t flex items-center justify-end gap-2 bg-surface-2 px-5 py-4 pb-safe sm:pb-4"
                >
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
