<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pause, Play, Plus, TriangleAlert } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Btn from '@/Components/UI/Btn.vue';
import Card from '@/Components/UI/Card.vue';
import ConfirmDelete from '@/Components/UI/ConfirmDelete.vue';
import Dot from '@/Components/UI/Dot.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Field from '@/Components/UI/Field.vue';
import Modal from '@/Components/UI/Modal.vue';
import Money from '@/Components/UI/Money.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import StatTile from '@/Components/UI/StatTile.vue';
import BarList from '@/Components/Charts/BarList.vue';
import { centsToInput, parseAmount } from '@/Composables/useMoney';
import { relativeDays } from '@/Composables/useDates';

const props = defineProps({
    subscriptions: { type: Array, required: true },
    categories: { type: Array, required: true },
    summary: { type: Object, required: true },
});

const BASE = '/abonnements';

const filter = ref('active');
const open = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    amount: '',
    cycle: 'monthly',
    category_id: '',
    next_due_on: '',
    is_active: true,
    notes: '',
});

form.transform((data) => ({
    ...data,
    amount: parseAmount(data.amount),
    category_id: data.category_id === '' ? null : data.category_id,
    next_due_on: data.next_due_on === '' ? null : data.next_due_on,
    notes: data.notes === '' ? null : data.notes,
}));

const categoryOptions = computed(() => props.categories.map((c) => ({ value: c.id, label: c.name })));

const filters = computed(() => [
    { value: 'active', label: 'Actifs', count: props.summary.active_count },
    { value: 'paused', label: 'En pause', count: props.summary.inactive_count },
    { value: 'all', label: 'Tous', count: props.subscriptions.length },
]);

const visible = computed(() => {
    if (filter.value === 'active') return props.subscriptions.filter((s) => s.is_active);
    if (filter.value === 'paused') return props.subscriptions.filter((s) => !s.is_active);

    return props.subscriptions;
});

/** Poids par catégorie, en coût annualisé : mensuels et annuels comparables. */
const byCategory = computed(() => {
    const map = new Map();

    for (const sub of props.subscriptions.filter((s) => s.is_active)) {
        const name = sub.category?.name ?? 'Sans catégorie';
        const color = sub.category?.color ?? null;

        if (!map.has(name)) map.set(name, { name, color, total_cents: 0 });
        map.get(name).total_cents += sub.yearly_cents;
    }

    const rows = [...map.values()].sort((a, b) => b.total_cents - a.total_cents);
    const total = rows.reduce((s, r) => s + r.total_cents, 0);

    return rows.map((r) => ({
        ...r,
        share: total > 0 ? Math.round((r.total_cents / total) * 1000) / 10 : 0,
    }));
});

const cycleLabel = (cycle) => (cycle === 'yearly' ? 'par an' : 'par mois');

/** Le serveur trie déjà par coût mensualisé décroissant : le premier actif gagne. */
const heaviest = computed(() => props.subscriptions.find((s) => s.is_active) ?? null);

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(sub) {
    editing.value = sub;
    form.clearErrors();
    form.name = sub.name;
    form.amount = centsToInput(sub.amount_cents);
    form.cycle = sub.cycle;
    form.category_id = sub.category?.id ?? '';
    form.next_due_on = sub.next_due_on ?? '';
    form.is_active = sub.is_active;
    form.notes = sub.notes ?? '';
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

/** Bascule actif/en pause sans ouvrir le formulaire. */
function togglePause(sub) {
    useForm({
        name: sub.name,
        amount: sub.amount_cents / 100,
        cycle: sub.cycle,
        category_id: sub.category?.id ?? null,
        next_due_on: sub.next_due_on,
        is_active: !sub.is_active,
        notes: sub.notes,
    }).put(`${BASE}/${sub.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout
        title="Abonnements"
        subtitle="Ce qui se prélève tout seul, chaque mois ou chaque année."
    >
        <template #actions>
            <Btn variant="solid" class="hidden sm:inline-flex" @click="openCreate">
                <Plus class="size-4" />
                Ajouter
            </Btn>
        </template>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatTile
                label="Charge fixe"
                :cents="summary.monthly_cents"
                tone="out"
                accent="var(--series-out)"
                hint="prélevé chaque mois, annuels lissés"
            />
            <StatTile
                label="Sur un an"
                :cents="summary.yearly_cents"
                tone="out"
                hint="coût annualisé de tous les actifs"
            />
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-[11px] font-medium tracking-wide text-ink-3 uppercase">Actifs</p>
                <p class="tnum mt-1.5 text-2xl leading-none text-ink sm:text-[1.75rem]">
                    {{ summary.active_count }}
                </p>
                <p v-if="summary.inactive_count" class="mt-1.5 text-xs text-ink-3">
                    + {{ summary.inactive_count }} en pause
                </p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-[11px] font-medium tracking-wide text-ink-3 uppercase">Le plus lourd</p>
                <p class="mt-1.5 truncate text-base text-ink">{{ heaviest?.name ?? '—' }}</p>
                <p v-if="heaviest" class="mt-1.5 text-xs text-ink-3">
                    <Money :cents="heaviest.monthly_cents" /> / mois
                </p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <SegmentedControl
                    v-model="filter"
                    :options="filters"
                    aria-label="Filtrer les abonnements"
                />

                <EmptyState
                    v-if="visible.length === 0"
                    title="Rien ici"
                    :description="
                        filter === 'paused'
                            ? 'Aucun abonnement en pause.'
                            : 'Ajoute tes abonnements pour voir ta charge fixe réelle.'
                    "
                >
                    <Btn v-if="filter !== 'paused'" variant="solid" @click="openCreate">
                        <Plus class="size-4" />
                        Ajouter
                    </Btn>
                </EmptyState>

                <Card v-else flush>
                    <ul>
                        <li
                            v-for="(sub, i) in visible"
                            :key="sub.id"
                            class="flex items-center gap-2 px-4 sm:px-5"
                            :class="[i > 0 ? 'hairline-t' : '', sub.is_active ? '' : 'opacity-55']"
                        >
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 py-3 text-left"
                                @click="openEdit(sub)"
                            >
                                <Dot :color="sub.category?.color" :size="9" />
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2">
                                        <span class="truncate text-sm text-ink">{{ sub.name }}</span>
                                        <span
                                            class="shrink-0 rounded bg-surface-2 px-1.5 py-0.5 text-[10px] text-ink-3"
                                        >{{ sub.cycle === 'yearly' ? 'annuel' : 'mensuel' }}</span>
                                    </span>
                                    <span class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-3">
                                        <TriangleAlert
                                            v-if="sub.notes?.includes('renégocier') || sub.notes?.includes('Non refacturé')"
                                            class="size-3 shrink-0 text-st-doing"
                                        />
                                        <span class="truncate">
                                            {{ sub.category?.name ?? 'Sans catégorie' }}
                                            <template v-if="sub.next_due_on">
                                                · {{ relativeDays(sub.next_due_on) }}
                                            </template>
                                            <template v-if="sub.notes"> · {{ sub.notes }}</template>
                                        </span>
                                    </span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <Money
                                        :cents="sub.amount_cents"
                                        tone="out"
                                        class="block text-sm font-medium"
                                    />
                                    <span class="block text-[10px] text-ink-3">
                                        {{ cycleLabel(sub.cycle) }}
                                    </span>
                                </span>
                            </button>

                            <button
                                type="button"
                                class="rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
                                :aria-label="sub.is_active ? `Mettre ${sub.name} en pause` : `Réactiver ${sub.name}`"
                                @click="togglePause(sub)"
                            >
                                <component :is="sub.is_active ? Pause : Play" class="size-4" />
                            </button>

                            <ConfirmDelete :url="`${BASE}/${sub.id}`" :label="sub.name" />
                        </li>
                    </ul>
                </Card>
            </div>

            <div class="space-y-4">
                <Card
                    title="Poids par catégorie"
                    subtitle="Coût annualisé, abonnements actifs"
                >
                    <BarList
                        v-if="byCategory.length"
                        :items="byCategory"
                        color="var(--series-out)"
                        :max="8"
                        show-dots
                    />
                    <p v-else class="text-sm text-ink-3">Aucun abonnement actif.</p>
                </Card>
            </div>
        </div>

        <button
            type="button"
            class="fixed right-4 bottom-24 z-40 flex size-13 items-center justify-center rounded-full bg-ink text-page shadow-xl transition active:scale-95 sm:hidden"
            aria-label="Ajouter un abonnement"
            @click="openCreate"
        >
            <Plus class="size-6" />
        </button>

        <Modal
            :open="open"
            :title="editing ? 'Modifier l\'abonnement' : 'Nouvel abonnement'"
            @close="open = false"
        >
            <form id="sub-form" class="space-y-4" @submit.prevent="submit">
                <Field
                    v-model="form.name"
                    label="Titre"
                    placeholder="Spotify, VPS, assurance…"
                    :error="form.errors.name"
                    required
                />

                <div class="grid grid-cols-2 gap-3">
                    <Field
                        v-model="form.amount"
                        label="Prix"
                        type="amount"
                        :error="form.errors.amount"
                        required
                    />
                    <Field
                        v-model="form.cycle"
                        label="Récurrence"
                        type="select"
                        :options="[
                            { value: 'monthly', label: 'Mensuel' },
                            { value: 'yearly', label: 'Annuel' },
                        ]"
                        :error="form.errors.cycle"
                        required
                    />
                </div>

                <Field
                    v-model="form.category_id"
                    label="Catégorie"
                    type="select"
                    :options="categoryOptions"
                    empty-option="Sans catégorie"
                    :error="form.errors.category_id"
                />

                <Field
                    v-model="form.next_due_on"
                    label="Prochaine échéance"
                    type="date"
                    :error="form.errors.next_due_on"
                    hint="Optionnel — sert à afficher les prélèvements à venir."
                />

                <Field
                    v-model="form.notes"
                    label="Note"
                    type="textarea"
                    :rows="2"
                    placeholder="Optionnel"
                    :error="form.errors.notes"
                />

                <label class="flex cursor-pointer items-start gap-3 rounded-lg bg-surface-2 p-3">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="mt-0.5 size-4 shrink-0 accent-[var(--ink)]"
                    />
                    <span>
                        <span class="block text-sm text-ink">Actif</span>
                        <span class="block text-xs text-ink-3">
                            Un abonnement en pause ne compte plus dans la charge fixe, mais reste
                            dans la liste.
                        </span>
                    </span>
                </label>
            </form>

            <template #footer>
                <Btn variant="ghost" @click="open = false">Annuler</Btn>
                <Btn variant="solid" type="submit" form="sub-form" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </Btn>
            </template>
        </Modal>
    </AppLayout>
</template>
