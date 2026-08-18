<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Plus, RotateCcw, TriangleAlert } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Btn from '@/Components/UI/Btn.vue';
import Card from '@/Components/UI/Card.vue';
import ConfirmDelete from '@/Components/UI/ConfirmDelete.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Field from '@/Components/UI/Field.vue';
import Modal from '@/Components/UI/Modal.vue';
import Money from '@/Components/UI/Money.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import StatTile from '@/Components/UI/StatTile.vue';
import BarList from '@/Components/Charts/BarList.vue';
import { centsToInput, parseAmount } from '@/Composables/useMoney';
import { formatFull, relativeDays } from '@/Composables/useDates';

const props = defineProps({
    invoices: { type: Array, required: true },
    summary: { type: Object, required: true },
    clients: { type: Array, required: true },
    incomeCategories: { type: Array, required: true },
    today: { type: String, required: true },
    defaultDueOn: { type: String, required: true },
});

const BASE = '/factures';

const STATUS = {
    draft: { label: 'Brouillon', klass: 'bg-surface-2 text-ink-3' },
    sent: { label: 'Envoyée', klass: 'bg-surface-2 text-ink-2' },
    paid: { label: 'Payée', klass: 'bg-pos-soft text-pos' },
    cancelled: { label: 'Annulée', klass: 'bg-surface-2 text-ink-3' },
};

const filter = ref('outstanding');
const open = ref(false);
const editing = ref(null);
const payTarget = ref(null);

const form = useForm({
    number: '',
    client: '',
    label: '',
    amount: '',
    vat_rate: 21,
    status: 'sent',
    issued_on: props.today,
    due_on: props.defaultDueOn,
    notes: '',
});

form.transform((data) => ({
    ...data,
    amount: parseAmount(data.amount),
    vat_rate: Number(data.vat_rate),
    number: data.number === '' ? null : data.number,
    notes: data.notes === '' ? null : data.notes,
}));

const payForm = useForm({ paid_on: props.today, category_id: '' });
payForm.transform((d) => ({ ...d, category_id: d.category_id === '' ? null : d.category_id }));

const filters = computed(() => [
    { value: 'outstanding', label: 'À encaisser', count: props.summary.outstanding_count },
    { value: 'overdue', label: 'En retard', count: props.summary.overdue_count },
    { value: 'paid', label: 'Payées', count: props.summary.paid_year_count },
    { value: 'all', label: 'Toutes', count: props.invoices.length },
]);

const visible = computed(() => {
    if (filter.value === 'outstanding') {
        return props.invoices.filter((i) => ['draft', 'sent'].includes(i.status));
    }
    if (filter.value === 'overdue') return props.invoices.filter((i) => i.is_overdue);
    if (filter.value === 'paid') return props.invoices.filter((i) => i.status === 'paid');

    return props.invoices;
});

/** Qui te doit le plus, tout de suite. */
const owedByClient = computed(() => {
    const map = new Map();

    for (const i of props.invoices.filter((x) => ['draft', 'sent'].includes(x.status))) {
        map.set(i.client, (map.get(i.client) ?? 0) + i.total_cents);
    }

    const rows = [...map.entries()]
        .map(([name, total_cents]) => ({ name, total_cents }))
        .sort((a, b) => b.total_cents - a.total_cents);

    const total = rows.reduce((s, r) => s + r.total_cents, 0);

    return rows.map((r) => ({
        ...r,
        share: total > 0 ? Math.round((r.total_cents / total) * 1000) / 10 : 0,
    }));
});

const previewTotal = computed(() => {
    const ht = parseAmount(form.amount);
    if (ht === '') return null;

    const cents = Math.round(ht * 100);

    return { ht: cents, vat: Math.round((cents * Number(form.vat_rate)) / 100) };
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.issued_on = props.today;
    form.due_on = props.defaultDueOn;
    open.value = true;
}

function openEdit(invoice) {
    editing.value = invoice;
    form.clearErrors();
    form.number = invoice.number ?? '';
    form.client = invoice.client;
    form.label = invoice.label;
    form.amount = centsToInput(invoice.amount_cents);
    form.vat_rate = invoice.vat_rate;
    form.status = invoice.status === 'paid' ? 'sent' : invoice.status;
    form.issued_on = invoice.issued_on;
    form.due_on = invoice.due_on;
    form.notes = invoice.notes ?? '';
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

    editing.value ? form.put(`${BASE}/${editing.value.id}`, options) : form.post(BASE, options);
}

function openPay(invoice) {
    payTarget.value = invoice;
    payForm.reset();
    payForm.clearErrors();
    payForm.paid_on = props.today;
}

function confirmPay() {
    payForm.post(`${BASE}/${payTarget.value.id}/encaisser`, {
        preserveScroll: true,
        onSuccess: () => (payTarget.value = null),
    });
}

function reopen(invoice) {
    router.post(`${BASE}/${invoice.id}/rouvrir`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Factures" subtitle="Ce qu'on te doit, et depuis combien de temps.">
        <template #actions>
            <Btn variant="solid" class="hidden sm:inline-flex" @click="openCreate">
                <Plus class="size-4" />
                Nouvelle facture
            </Btn>
        </template>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <StatTile
                label="On te doit"
                :cents="summary.outstanding_cents"
                accent="var(--series-in)"
                :hint="`${summary.outstanding_count} facture${summary.outstanding_count > 1 ? 's' : ''} ouverte${summary.outstanding_count > 1 ? 's' : ''}`"
            />
            <StatTile
                label="En retard"
                :cents="summary.overdue_cents"
                :tone="summary.overdue_cents > 0 ? 'out' : 'none'"
                :hint="
                    summary.overdue_count
                        ? `la plus vieille : ${summary.worst_days_late} jours`
                        : 'rien en retard'
                "
            />
            <StatTile
                label="Encaissé cette année"
                :cents="summary.paid_year_cents"
                tone="in"
                :hint="`${summary.paid_year_count} facture${summary.paid_year_count > 1 ? 's' : ''}`"
            />
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-[11px] font-medium tracking-wide text-ink-3 uppercase">
                    TVA encaissée
                </p>
                <p class="mt-1.5 text-2xl leading-none sm:text-[1.75rem]">
                    <Money :cents="summary.vat_collected_cents" />
                </p>
                <p class="mt-1.5 text-xs text-ink-3">à reverser — ce n'est pas ton argent</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <SegmentedControl v-model="filter" :options="filters" aria-label="Filtrer" />

                <EmptyState
                    v-if="visible.length === 0"
                    :title="filter === 'all' ? 'Aucune facture' : 'Rien ici'"
                    description="Enregistre une facture dès que tu l'envoies : c'est le seul moyen de savoir ce qu'on te doit."
                >
                    <Btn variant="solid" @click="openCreate">
                        <Plus class="size-4" />
                        Nouvelle facture
                    </Btn>
                </EmptyState>

                <Card v-else flush>
                    <ul>
                        <li
                            v-for="(inv, i) in visible"
                            :key="inv.id"
                            class="flex items-center gap-2 px-4 sm:px-5"
                            :class="i > 0 ? 'hairline-t' : ''"
                        >
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 py-3 text-left"
                                @click="openEdit(inv)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="truncate text-sm font-medium text-ink">
                                            {{ inv.client }}
                                        </span>
                                        <span
                                            class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                                            :class="STATUS[inv.status].klass"
                                        >{{ STATUS[inv.status].label }}</span>
                                        <span
                                            v-if="inv.is_overdue"
                                            class="flex items-center gap-1 rounded bg-neg-soft px-1.5 py-0.5 text-[10px] font-medium text-neg"
                                        >
                                            <TriangleAlert class="size-3" />
                                            {{ inv.days_late }} j de retard
                                        </span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-ink-3">
                                        <template v-if="inv.number">{{ inv.number }} · </template>
                                        {{ inv.label }} ·
                                        <template v-if="inv.status === 'paid'">
                                            encaissée le {{ formatFull(inv.paid_on) }}
                                        </template>
                                        <template v-else>
                                            échéance {{ relativeDays(inv.due_on) }}
                                        </template>
                                    </span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <Money
                                        :cents="inv.total_cents"
                                        :tone="inv.status === 'paid' ? 'in' : 'none'"
                                        class="block text-sm font-medium"
                                    />
                                    <span class="tnum block text-[10px] text-ink-3">
                                        dont {{ (inv.vat_cents / 100).toFixed(2).replace('.', ',') }} € TVA
                                    </span>
                                </span>
                            </button>

                            <button
                                v-if="inv.status !== 'paid' && inv.status !== 'cancelled'"
                                type="button"
                                class="rounded-lg p-2 text-ink-3 transition hover:bg-pos-soft hover:text-pos"
                                :aria-label="`Encaisser la facture de ${inv.client}`"
                                @click="openPay(inv)"
                            >
                                <Check class="size-4" />
                            </button>
                            <button
                                v-else-if="inv.status === 'paid'"
                                type="button"
                                class="rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
                                :aria-label="`Rouvrir la facture de ${inv.client}`"
                                @click="reopen(inv)"
                            >
                                <RotateCcw class="size-4" />
                            </button>

                            <ConfirmDelete
                                :url="`${BASE}/${inv.id}`"
                                :label="`la facture ${inv.client} — ${inv.label}`"
                                :consequence="
                                    inv.status === 'paid'
                                        ? 'La rentrée créée à l\'encaissement sera retirée aussi.'
                                        : null
                                "
                            />
                        </li>
                    </ul>
                </Card>
            </div>

            <Card title="Qui te doit le plus" subtitle="Factures ouvertes, TVA comprise">
                <BarList
                    v-if="owedByClient.length"
                    :items="owedByClient"
                    color="var(--series-in)"
                    :max="8"
                />
                <p v-else class="text-sm text-ink-3">Personne ne te doit rien.</p>
            </Card>
        </div>

        <button
            type="button"
            class="fixed right-4 bottom-24 z-40 flex size-13 items-center justify-center rounded-full bg-ink text-page shadow-xl transition active:scale-95 sm:hidden"
            aria-label="Nouvelle facture"
            @click="openCreate"
        >
            <Plus class="size-6" />
        </button>

        <!-- ===== Facture ===== -->
        <Modal
            :open="open"
            :title="editing ? 'Modifier la facture' : 'Nouvelle facture'"
            @close="open = false"
        >
            <form id="invoice-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <Field
                        v-model="form.client"
                        label="Client"
                        placeholder="Nom du client"
                        :suggestions="clients"
                        :error="form.errors.client"
                        required
                    />
                    <Field
                        v-model="form.number"
                        label="Numéro"
                        placeholder="2026-014"
                        :error="form.errors.number"
                    />
                </div>

                <Field
                    v-model="form.label"
                    label="Prestation"
                    placeholder="Création site web, maintenance…"
                    :error="form.errors.label"
                    required
                />

                <div class="grid grid-cols-2 gap-3">
                    <Field
                        v-model="form.amount"
                        label="Montant HT"
                        type="amount"
                        :error="form.errors.amount"
                        required
                    />
                    <Field
                        v-model="form.vat_rate"
                        label="TVA"
                        type="select"
                        :options="[
                            { value: 21, label: '21 %' },
                            { value: 6, label: '6 %' },
                            { value: 0, label: '0 % (exonéré)' },
                        ]"
                        :error="form.errors.vat_rate"
                        required
                    />
                </div>

                <div
                    v-if="previewTotal"
                    class="flex items-baseline justify-between rounded-lg bg-surface-2 px-3 py-2.5"
                >
                    <span class="text-xs text-ink-3">Le client doit virer</span>
                    <span class="text-sm font-medium">
                        <Money :cents="previewTotal.ht + previewTotal.vat" />
                        <span class="ml-1 text-xs font-normal text-ink-3">
                            dont <Money :cents="previewTotal.vat" /> de TVA
                        </span>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <Field
                        v-model="form.issued_on"
                        label="Émise le"
                        type="date"
                        :error="form.errors.issued_on"
                        required
                    />
                    <Field
                        v-model="form.due_on"
                        label="Échéance"
                        type="date"
                        :error="form.errors.due_on"
                        required
                    />
                </div>

                <Field
                    v-model="form.status"
                    label="Statut"
                    type="select"
                    :options="[
                        { value: 'sent', label: 'Envoyée' },
                        { value: 'draft', label: 'Brouillon' },
                        { value: 'cancelled', label: 'Annulée' },
                    ]"
                    :error="form.errors.status"
                    hint="« Payée » se met via le bouton ✓ de la liste : ça crée la rentrée."
                    required
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
                <Btn variant="solid" type="submit" form="invoice-form" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </Btn>
            </template>
        </Modal>

        <!-- ===== Encaissement ===== -->
        <Modal
            :open="payTarget !== null"
            title="Encaisser la facture"
            :description="payTarget ? `${payTarget.client} — ${payTarget.label}` : null"
            @close="payTarget = null"
        >
            <form v-if="payTarget" id="pay-form" class="space-y-4" @submit.prevent="confirmPay">
                <div class="flex items-baseline justify-between rounded-lg bg-surface-2 px-3 py-2.5">
                    <span class="text-xs text-ink-3">Montant reçu</span>
                    <Money :cents="payTarget.total_cents" class="text-base font-medium" />
                </div>

                <Field
                    v-model="payForm.paid_on"
                    label="Reçu le"
                    type="date"
                    :error="payForm.errors.paid_on"
                    required
                />

                <Field
                    v-model="payForm.category_id"
                    label="Catégorie de la rentrée"
                    type="select"
                    :options="incomeCategories.map((c) => ({ value: c.id, label: c.name }))"
                    empty-option="Sans catégorie"
                    :error="payForm.errors.category_id"
                />

                <p class="text-xs text-ink-3">
                    Une rentrée de
                    <Money :cents="payTarget.total_cents" />
                    sera créée automatiquement : tu n'as pas à la ressaisir.
                </p>
            </form>

            <template #footer>
                <Btn variant="ghost" @click="payTarget = null">Annuler</Btn>
                <Btn variant="solid" type="submit" form="pay-form" :disabled="payForm.processing">
                    {{ payForm.processing ? 'Enregistrement…' : 'Encaisser' }}
                </Btn>
            </template>
        </Modal>
    </AppLayout>
</template>
