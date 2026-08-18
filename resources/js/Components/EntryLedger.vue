<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CopyPlus, Plus } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Btn from '@/Components/UI/Btn.vue';
import Card from '@/Components/UI/Card.vue';
import ConfirmDelete from '@/Components/UI/ConfirmDelete.vue';
import Dot from '@/Components/UI/Dot.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Field from '@/Components/UI/Field.vue';
import Modal from '@/Components/UI/Modal.vue';
import Money from '@/Components/UI/Money.vue';
import MonthNav from '@/Components/UI/MonthNav.vue';
import StatTile from '@/Components/UI/StatTile.vue';
import BarList from '@/Components/Charts/BarList.vue';
import { centsToInput, parseAmount } from '@/Composables/useMoney';
import { formatFull } from '@/Composables/useDates';

/**
 * Journal d'écritures daté — mutualisé par Dépenses et Rentrées, qui ont la
 * même structure (libellé, montant, catégorie, date) et ne diffèrent que par
 * le vocabulaire, la colonne de date et la couleur de série.
 */
const props = defineProps({
    entries: { type: Array, required: true },
    categories: { type: Array, required: true },
    month: { type: Object, required: true },
    totalCents: { type: Number, required: true },
    basePath: { type: String, required: true },
    /** 'spent_on' | 'received_on' */
    dateKey: { type: String, required: true },
    /** 'out' | 'in' */
    tone: { type: String, required: true },
    seriesColor: { type: String, required: true },
    labels: { type: Object, required: true },
    /** Libellés déjà saisis, avec dernier montant et catégorie. */
    suggestions: { type: Array, default: () => [] },
});

const open = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    amount: '',
    category_id: '',
    [props.dateKey]: props.month.today,
    notes: '',
});

form.transform((data) => ({
    ...data,
    amount: parseAmount(data.amount),
    category_id: data.category_id === '' ? null : data.category_id,
    notes: data.notes === '' ? null : data.notes,
}));

const categoryOptions = computed(() =>
    props.categories.map((c) => ({ value: c.id, label: c.name })),
);

const suggestionNames = computed(() => props.suggestions.map((s) => s.name));

/**
 * Dès qu'un libellé connu est saisi en entier, on propose le montant et la
 * catégorie de la dernière fois. On n'écrase que les champs encore vides :
 * une correction manuelle ne doit jamais être annulée par l'autocomplétion.
 */
function applySuggestion(name) {
    const match = props.suggestions.find(
        (s) => s.name.toLowerCase() === String(name).trim().toLowerCase(),
    );

    if (!match) return;

    if (form.amount === '') form.amount = centsToInput(match.amount_cents);
    if (form.category_id === '' && match.category_id) form.category_id = match.category_id;
}

/** Regroupé par jour : on lit un mois comme une suite de journées. */
const days = computed(() => {
    const map = new Map();

    for (const entry of props.entries) {
        const key = entry[props.dateKey];
        if (!map.has(key)) map.set(key, { date: key, entries: [], total: 0 });

        const day = map.get(key);
        day.entries.push(entry);
        day.total += entry.amount_cents;
    }

    return [...map.values()];
});

/** Répartition du mois par catégorie, calculée côté client depuis la liste. */
const byCategory = computed(() => {
    const map = new Map();

    for (const entry of props.entries) {
        const name = entry.category?.name ?? 'Sans catégorie';
        const color = entry.category?.color ?? null;

        if (!map.has(name)) map.set(name, { name, color, total_cents: 0 });
        map.get(name).total_cents += entry.amount_cents;
    }

    const rows = [...map.values()].sort((a, b) => b.total_cents - a.total_cents);
    const total = rows.reduce((sum, r) => sum + r.total_cents, 0);

    return rows.map((r) => ({
        ...r,
        share: total > 0 ? Math.round((r.total_cents / total) * 1000) / 10 : 0,
    }));
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form[props.dateKey] = props.month.today;
    open.value = true;
}

/**
 * « Refaire la même » : rouvre le formulaire pré-rempli, mais daté
 * d'aujourd'hui et en création. Deux gestes pour une dépense récurrente au
 * lieu de quatre champs à retaper.
 */
function duplicate(entry) {
    editing.value = null;
    form.clearErrors();
    form.name = entry.name;
    form.amount = centsToInput(entry.amount_cents);
    form.category_id = entry.category?.id ?? '';
    form[props.dateKey] = props.month.today;
    form.notes = entry.notes ?? '';
    open.value = true;
}

function openEdit(entry) {
    editing.value = entry;
    form.clearErrors();
    form.name = entry.name;
    form.amount = centsToInput(entry.amount_cents);
    form.category_id = entry.category?.id ?? '';
    form[props.dateKey] = entry[props.dateKey];
    form.notes = entry.notes ?? '';
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
        form.put(`${props.basePath}/${editing.value.id}`, options);
    } else {
        form.post(props.basePath, options);
    }
}
</script>

<template>
    <AppLayout :title="labels.title">
        <template #actions>
            <MonthNav :month="month" :base-path="basePath" />
            <Btn variant="solid" class="hidden sm:inline-flex" @click="openCreate">
                <Plus class="size-4" />
                {{ labels.addCta }}
            </Btn>
        </template>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- ===== Colonne principale : le journal ===== -->
            <div class="space-y-4 lg:col-span-2">
                <StatTile
                    :label="labels.totalLabel"
                    :cents="totalCents"
                    :tone="tone"
                    :accent="seriesColor"
                    :hint="`${entries.length} écriture${entries.length > 1 ? 's' : ''} en ${month.label}`"
                />

                <EmptyState
                    v-if="entries.length === 0"
                    :title="labels.emptyTitle"
                    :description="labels.emptyDescription"
                >
                    <Btn variant="solid" @click="openCreate">
                        <Plus class="size-4" />
                        {{ labels.addCta }}
                    </Btn>
                </EmptyState>

                <Card v-else flush>
                    <div v-for="(day, i) in days" :key="day.date" :class="i > 0 ? 'hairline-t' : ''">
                        <div
                            class="flex items-baseline justify-between gap-3 bg-surface-2 px-4 py-2 sm:px-5"
                        >
                            <span class="text-xs font-medium text-ink-2">{{ formatFull(day.date) }}</span>
                            <Money :cents="day.total" class="text-xs text-ink-3" />
                        </div>

                        <ul>
                            <li
                                v-for="entry in day.entries"
                                :key="entry.id"
                                class="hairline-t flex items-center gap-3 px-4 first:border-t-0 sm:px-5"
                            >
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 items-center gap-3 py-3 text-left"
                                    @click="openEdit(entry)"
                                >
                                    <Dot :color="entry.category?.color" :size="9" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-ink">{{ entry.name }}</span>
                                        <span class="block truncate text-xs text-ink-3">
                                            <template v-if="entry.invoice">
                                                Facture {{ entry.invoice.client }} ·
                                            </template>
                                            {{ entry.category?.name ?? 'Sans catégorie' }}
                                            <template v-if="entry.notes"> · {{ entry.notes }}</template>
                                        </span>
                                    </span>
                                    <Money
                                        :cents="entry.amount_cents"
                                        :tone="tone"
                                        class="shrink-0 text-sm font-medium"
                                    />
                                </button>

                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
                                    :aria-label="`Refaire la même : ${entry.name}`"
                                    title="Refaire la même, datée d'aujourd'hui"
                                    @click="duplicate(entry)"
                                >
                                    <CopyPlus class="size-4" />
                                </button>

                                <ConfirmDelete
                                    :url="`${basePath}/${entry.id}`"
                                    :label="entry.name"
                                    :consequence="
                                        entry.invoice
                                            ? `La facture ${entry.invoice.client} — ${entry.invoice.label} repassera en « à encaisser ». Elle n'est pas supprimée : le client te doit toujours cet argent.`
                                            : null
                                    "
                                />
                            </li>
                        </ul>
                    </div>
                </Card>
            </div>

            <!-- ===== Colonne latérale : la répartition ===== -->
            <div class="lg:col-span-1">
                <Card
                    :title="labels.breakdownTitle"
                    :subtitle="entries.length ? `Sur ${month.label}` : null"
                >
                    <BarList
                        v-if="byCategory.length"
                        :items="byCategory"
                        :color="seriesColor"
                        :max="8"
                        show-dots
                    />
                    <p v-else class="text-sm text-ink-3">
                        Rien à répartir pour ce mois.
                    </p>
                </Card>
            </div>
        </div>

        <!-- Bouton flottant : ajout à portée de pouce sur mobile -->
        <button
            type="button"
            class="fixed right-4 bottom-24 z-40 flex size-13 items-center justify-center rounded-full bg-ink text-page shadow-xl transition active:scale-95 sm:hidden"
            :aria-label="labels.addCta"
            @click="openCreate"
        >
            <Plus class="size-6" />
        </button>

        <!-- ===== Formulaire ===== -->
        <Modal
            :open="open"
            :title="editing ? labels.editTitle : labels.createTitle"
            @close="open = false"
        >
            <form :id="`entry-form-${dateKey}`" class="space-y-4" @submit.prevent="submit">
                <Field
                    v-model="form.name"
                    :label="labels.nameLabel"
                    :placeholder="labels.namePlaceholder"
                    :suggestions="suggestionNames"
                    :error="form.errors.name"
                    required
                    @update:model-value="applySuggestion"
                />

                <div class="grid grid-cols-2 gap-3">
                    <Field
                        v-model="form.amount"
                        label="Montant"
                        type="amount"
                        :error="form.errors.amount"
                        required
                    />
                    <Field
                        v-model="form[dateKey]"
                        label="Date"
                        type="date"
                        :error="form.errors[dateKey]"
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
                    hint="Pour savoir où va (ou d'où vient) ton argent."
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
                <Btn
                    variant="solid"
                    type="submit"
                    :form="`entry-form-${dateKey}`"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </Btn>
            </template>
        </Modal>
    </AppLayout>
</template>
