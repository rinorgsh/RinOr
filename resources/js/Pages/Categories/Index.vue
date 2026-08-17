<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Btn from '@/Components/UI/Btn.vue';
import Card from '@/Components/UI/Card.vue';
import ConfirmDelete from '@/Components/UI/ConfirmDelete.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Field from '@/Components/UI/Field.vue';
import Modal from '@/Components/UI/Modal.vue';
import Money from '@/Components/UI/Money.vue';

const props = defineProps({
    categories: { type: Array, required: true },
});

const BASE = '/categories';

/* Teintes proposées : les 8 slots catégoriels validés + un neutre. */
const SWATCHES = [
    '#2a78d6', '#eb6834', '#1baf7a', '#eda100',
    '#e87ba4', '#008300', '#4a3aa7', '#e34948', '#898781',
];

const open = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    type: 'expense',
    color: '#2a78d6',
});

const groups = computed(() => [
    {
        type: 'expense',
        title: 'Dépenses',
        subtitle: 'Utilisées par les dépenses et les abonnements.',
        items: props.categories.filter((c) => c.type === 'expense'),
    },
    {
        type: 'income',
        title: 'Rentrées',
        subtitle: "Utilisées par les rentrées d'argent.",
        items: props.categories.filter((c) => c.type === 'income'),
    },
]);

function openCreate(type) {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.type = type;
    open.value = true;
}

function openEdit(category) {
    editing.value = category;
    form.clearErrors();
    form.name = category.name;
    form.type = category.type;
    form.color = category.color;
    open.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.put(`${BASE}/${editing.value.id}`, options);
    } else {
        form.post(BASE, options);
    }
}
</script>

<template>
    <AppLayout
        title="Catégories"
        subtitle="Elles répondent à une question : dans quoi part mon argent, et d'où il vient."
    >
        <div class="grid gap-4 lg:grid-cols-2">
            <Card v-for="group in groups" :key="group.type" flush>
                <template #header>
                    <div>
                        <h2 class="text-lg text-ink">{{ group.title }}</h2>
                        <p class="mt-0.5 text-xs text-ink-3">{{ group.subtitle }}</p>
                    </div>
                </template>
                <template #action>
                    <Btn variant="outline" size="sm" @click="openCreate(group.type)">
                        <Plus class="size-3.5" />
                        Ajouter
                    </Btn>
                </template>

                <EmptyState
                    v-if="group.items.length === 0"
                    title="Aucune catégorie"
                    description="Ajoute-en une pour commencer à classer."
                    class="mx-4 mb-4 sm:mx-5 sm:mb-5"
                />

                <ul v-else>
                    <li
                        v-for="(cat, i) in group.items"
                        :key="cat.id"
                        class="hairline-t flex items-center gap-3 px-4 sm:px-5"
                    >
                        <span
                            class="size-3 shrink-0 rounded-full"
                            :style="{ backgroundColor: cat.color }"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1 py-3">
                            <p class="truncate text-sm text-ink">{{ cat.name }}</p>
                            <p class="text-xs text-ink-3">
                                <template v-if="cat.usage_count">
                                    {{ cat.usage_count }} écriture{{ cat.usage_count > 1 ? 's' : '' }} ·
                                    <Money :cents="cat.total_cents" />
                                </template>
                                <template v-else>Jamais utilisée</template>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
                            :aria-label="`Modifier ${cat.name}`"
                            @click="openEdit(cat)"
                        >
                            <Pencil class="size-4" />
                        </button>
                        <ConfirmDelete
                            :url="`${BASE}/${cat.id}`"
                            :label="cat.name"
                            :consequence="
                                cat.usage_count
                                    ? `Ses ${cat.usage_count} écriture(s) sont conservées et repassent en « Sans catégorie ».`
                                    : null
                            "
                        />
                    </li>
                </ul>
            </Card>
        </div>

        <Modal
            :open="open"
            :title="editing ? 'Modifier la catégorie' : 'Nouvelle catégorie'"
            @close="open = false"
        >
            <form id="cat-form" class="space-y-4" @submit.prevent="submit">
                <Field
                    v-model="form.name"
                    label="Nom"
                    placeholder="Alimentation, Transport, Salaire…"
                    :error="form.errors.name"
                    required
                />

                <Field
                    v-model="form.type"
                    label="Type"
                    type="select"
                    :options="[
                        { value: 'expense', label: 'Dépense (et abonnements)' },
                        { value: 'income', label: 'Rentrée' },
                    ]"
                    :error="form.errors.type"
                    required
                />

                <div>
                    <Field v-model="form.color" label="Couleur" type="color" :error="form.errors.color" />

                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button
                            v-for="hex in SWATCHES"
                            :key="hex"
                            type="button"
                            class="size-7 rounded-md border transition"
                            :class="
                                form.color.toLowerCase() === hex
                                    ? 'border-ink scale-110'
                                    : 'border-line hover:scale-105'
                            "
                            :style="{ backgroundColor: hex }"
                            :aria-label="`Choisir la couleur ${hex}`"
                            @click="form.color = hex"
                        />
                    </div>
                </div>
            </form>

            <template #footer>
                <Btn variant="ghost" @click="open = false">Annuler</Btn>
                <Btn variant="solid" type="submit" form="cat-form" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </Btn>
            </template>
        </Modal>
    </AppLayout>
</template>
