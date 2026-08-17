<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import Modal from './Modal.vue';
import Btn from './Btn.vue';

const props = defineProps({
    url: { type: String, required: true },
    label: { type: String, required: true },
    /** Précision affichée dans la confirmation (ex. « et ses 12 mouvements »). */
    consequence: { type: String, default: null },
    iconOnly: { type: Boolean, default: true },
});

const open = ref(false);
const busy = ref(false);

function confirm() {
    busy.value = true;
    router.delete(props.url, {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
            open.value = false;
        },
    });
}
</script>

<template>
    <button
        type="button"
        class="rounded-lg p-2 text-ink-3 transition hover:bg-neg-soft hover:text-neg"
        :aria-label="`Supprimer ${label}`"
        @click="open = true"
    >
        <Trash2 class="size-4" />
        <span v-if="!iconOnly" class="ml-1.5 text-xs">Supprimer</span>
    </button>

    <Modal :open="open" title="Confirmer la suppression" @close="open = false">
        <p class="text-sm text-ink-2">
            Supprimer <span class="font-medium text-ink">{{ label }}</span> ?
        </p>
        <p v-if="consequence" class="mt-2 text-sm text-ink-3">{{ consequence }}</p>
        <p class="mt-2 text-sm text-ink-3">Cette action est définitive.</p>

        <template #footer>
            <Btn variant="ghost" @click="open = false">Annuler</Btn>
            <Btn variant="solid" :disabled="busy" @click="confirm">
                {{ busy ? 'Suppression…' : 'Supprimer' }}
            </Btn>
        </template>
    </Modal>
</template>
