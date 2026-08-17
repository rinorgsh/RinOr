<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Circle, CircleCheck, CircleDot, Plus, TriangleAlert } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Btn from '@/Components/UI/Btn.vue';
import Card from '@/Components/UI/Card.vue';
import ConfirmDelete from '@/Components/UI/ConfirmDelete.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Field from '@/Components/UI/Field.vue';
import Modal from '@/Components/UI/Modal.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import { relativeDays } from '@/Composables/useDates';

const props = defineProps({
    tasks: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const BASE = '/a-faire';

/**
 * Trois statuts, et le cycle est un simple clic sur la pastille :
 * à faire → en cours → terminé → à faire.
 */
const STATUS = {
    todo: { label: 'À faire', icon: Circle, klass: 'text-ink-3' },
    doing: { label: 'En cours', icon: CircleDot, klass: 'text-st-doing' },
    done: { label: 'Terminé', icon: CircleCheck, klass: 'text-st-done' },
};

const NEXT_STATUS = { todo: 'doing', doing: 'done', done: 'todo' };

const PRIORITY = {
    high: { label: 'Haute', klass: 'text-st-high bg-neg-soft' },
    normal: { label: 'Normale', klass: 'text-ink-2 bg-surface-2' },
    low: { label: 'Basse', klass: 'text-ink-3 bg-surface-2' },
};

const filter = ref('open');
const open = ref(false);
const editing = ref(null);

const form = useForm({
    title: '',
    status: 'todo',
    priority: 'normal',
    due_on: '',
    notes: '',
});

form.transform((data) => ({
    ...data,
    due_on: data.due_on === '' ? null : data.due_on,
    notes: data.notes === '' ? null : data.notes,
}));

const filters = computed(() => [
    { value: 'open', label: 'En cours', count: props.counts.todo + props.counts.doing },
    { value: 'done', label: 'Terminé', count: props.counts.done },
    { value: 'all', label: 'Tout', count: props.tasks.length },
]);

const visible = computed(() => {
    if (filter.value === 'open') return props.tasks.filter((t) => t.status !== 'done');
    if (filter.value === 'done') return props.tasks.filter((t) => t.status === 'done');

    return props.tasks;
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(task) {
    editing.value = task;
    form.clearErrors();
    form.title = task.title;
    form.status = task.status;
    form.priority = task.priority;
    form.due_on = task.due_on ?? '';
    form.notes = task.notes ?? '';
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

/** Avance le statut d'un cran, sans passer par le formulaire. */
function cycleStatus(task) {
    router.put(
        `${BASE}/${task.id}`,
        {
            title: task.title,
            status: NEXT_STATUS[task.status],
            priority: task.priority,
            due_on: task.due_on,
            notes: task.notes,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout title="À faire" subtitle="Tout ce que tu dois traiter, avec son statut.">
        <template #actions>
            <Btn variant="solid" class="hidden sm:inline-flex" @click="openCreate">
                <Plus class="size-4" />
                Ajouter
            </Btn>
        </template>

        <div
            v-if="counts.overdue > 0"
            class="mb-4 flex items-center gap-2.5 rounded-xl border border-line bg-st-doing-soft px-4 py-3"
        >
            <TriangleAlert class="size-4 shrink-0 text-st-doing" />
            <p class="text-sm text-ink">
                <span class="font-medium">{{ counts.overdue }}</span>
                tâche{{ counts.overdue > 1 ? 's' : '' }} en retard.
            </p>
        </div>

        <SegmentedControl
            v-model="filter"
            :options="filters"
            aria-label="Filtrer les tâches"
            class="mb-4"
        />

        <EmptyState
            v-if="visible.length === 0"
            :title="filter === 'done' ? 'Rien de terminé' : 'Rien à faire'"
            :description="
                filter === 'done'
                    ? 'Les tâches terminées apparaîtront ici.'
                    : 'Note ce que tu dois traiter : relances, renégociations, factures à envoyer.'
            "
        >
            <Btn v-if="filter !== 'done'" variant="solid" @click="openCreate">
                <Plus class="size-4" />
                Ajouter
            </Btn>
        </EmptyState>

        <Card v-else flush>
            <ul>
                <li
                    v-for="(task, i) in visible"
                    :key="task.id"
                    class="flex items-start gap-2 px-3 sm:px-4"
                    :class="i > 0 ? 'hairline-t' : ''"
                >
                    <!-- Pastille de statut : icône + libellé accessible, jamais la couleur seule -->
                    <button
                        type="button"
                        class="mt-2.5 shrink-0 rounded-full p-1.5 transition hover:bg-surface-2"
                        :class="STATUS[task.status].klass"
                        :aria-label="`${task.title} — ${STATUS[task.status].label}. Passer à « ${STATUS[NEXT_STATUS[task.status]].label} »`"
                        @click="cycleStatus(task)"
                    >
                        <component :is="STATUS[task.status].icon" class="size-5" />
                    </button>

                    <button
                        type="button"
                        class="min-w-0 flex-1 py-3 text-left"
                        @click="openEdit(task)"
                    >
                        <span
                            class="block text-sm"
                            :class="task.status === 'done' ? 'text-ink-3 line-through' : 'text-ink'"
                        >{{ task.title }}</span>

                        <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                                :class="PRIORITY[task.priority].klass"
                            >{{ PRIORITY[task.priority].label }}</span>

                            <span class="text-xs text-ink-3">{{ STATUS[task.status].label }}</span>

                            <span
                                v-if="task.due_on"
                                class="flex items-center gap-1 text-xs"
                                :class="task.is_overdue ? 'font-medium text-neg' : 'text-ink-3'"
                            >
                                <TriangleAlert v-if="task.is_overdue" class="size-3" />
                                {{ relativeDays(task.due_on) }}
                            </span>

                            <span v-if="task.notes" class="truncate text-xs text-ink-3">
                                · {{ task.notes }}
                            </span>
                        </span>
                    </button>

                    <div class="mt-1.5 shrink-0">
                        <ConfirmDelete :url="`${BASE}/${task.id}`" :label="task.title" />
                    </div>
                </li>
            </ul>
        </Card>

        <button
            type="button"
            class="fixed right-4 bottom-24 z-40 flex size-13 items-center justify-center rounded-full bg-ink text-page shadow-xl transition active:scale-95 sm:hidden"
            aria-label="Ajouter une tâche"
            @click="openCreate"
        >
            <Plus class="size-6" />
        </button>

        <Modal
            :open="open"
            :title="editing ? 'Modifier la tâche' : 'Nouvelle tâche'"
            @close="open = false"
        >
            <form id="task-form" class="space-y-4" @submit.prevent="submit">
                <Field
                    v-model="form.title"
                    label="Quoi ?"
                    placeholder="Relancer un client, envoyer une facture…"
                    :error="form.errors.title"
                    required
                />

                <div class="grid grid-cols-2 gap-3">
                    <Field
                        v-model="form.status"
                        label="Statut"
                        type="select"
                        :options="[
                            { value: 'todo', label: 'À faire' },
                            { value: 'doing', label: 'En cours' },
                            { value: 'done', label: 'Terminé' },
                        ]"
                        :error="form.errors.status"
                        required
                    />
                    <Field
                        v-model="form.priority"
                        label="Priorité"
                        type="select"
                        :options="[
                            { value: 'high', label: 'Haute' },
                            { value: 'normal', label: 'Normale' },
                            { value: 'low', label: 'Basse' },
                        ]"
                        :error="form.errors.priority"
                        required
                    />
                </div>

                <Field
                    v-model="form.due_on"
                    label="Échéance"
                    type="date"
                    :error="form.errors.due_on"
                    hint="Optionnel"
                />

                <Field
                    v-model="form.notes"
                    label="Note"
                    type="textarea"
                    :rows="2"
                    placeholder="Optionnel"
                    :error="form.errors.notes"
                />
            </form>

            <template #footer>
                <Btn variant="ghost" @click="open = false">Annuler</Btn>
                <Btn variant="solid" type="submit" form="task-form" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </Btn>
            </template>
        </Modal>
    </AppLayout>
</template>
