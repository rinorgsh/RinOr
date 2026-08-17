<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Pencil, Plus } from '@lucide/vue';
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
import { centsToInput, parseAmount } from '@/Composables/useMoney';
import { formatFull } from '@/Composables/useDates';

const props = defineProps({
    treasuries: { type: Array, required: true },
    total_cents: { type: Number, required: true },
    today: { type: String, required: true },
});

const BASE = '/tresorerie';

/* ---------- Mouvement (entrée / sortie) ---------- */
const moveOpen = ref(false);
const moveTarget = ref(null);

const moveForm = useForm({
    direction: 'in',
    amount: '',
    label: '',
    occurred_on: props.today,
    notes: '',
});

moveForm.transform((data) => ({
    ...data,
    amount: parseAmount(data.amount),
    notes: data.notes === '' ? null : data.notes,
}));

function openMovement(treasury, direction) {
    moveTarget.value = treasury;
    moveForm.reset();
    moveForm.clearErrors();
    moveForm.direction = direction;
    moveForm.occurred_on = props.today;
    moveOpen.value = true;
}

function submitMovement() {
    moveForm.post(`${BASE}/${moveTarget.value.id}/mouvements`, {
        preserveScroll: true,
        onSuccess: () => {
            moveOpen.value = false;
            moveForm.reset();
        },
    });
}

const isIn = computed(() => moveForm.direction === 'in');

/* ---------- Caisse ---------- */
const caisseOpen = ref(false);
const editingCaisse = ref(null);

const caisseForm = useForm({
    name: '',
    description: '',
    color: '#1baf7a',
    target: '',
});

caisseForm.transform((data) => ({
    ...data,
    target: data.target === '' ? null : parseAmount(data.target),
    description: data.description === '' ? null : data.description,
}));

function openCaisseCreate() {
    editingCaisse.value = null;
    caisseForm.reset();
    caisseForm.clearErrors();
    caisseOpen.value = true;
}

function openCaisseEdit(t) {
    editingCaisse.value = t;
    caisseForm.clearErrors();
    caisseForm.name = t.name;
    caisseForm.description = t.description ?? '';
    caisseForm.color = t.color;
    caisseForm.target = t.target_cents ? centsToInput(t.target_cents) : '';
    caisseOpen.value = true;
}

function submitCaisse() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            caisseOpen.value = false;
            caisseForm.reset();
        },
    };

    if (editingCaisse.value) {
        caisseForm.put(`${BASE}/${editingCaisse.value.id}`, options);
    } else {
        caisseForm.post(BASE, options);
    }
}

/** Avancement vers l'objectif, plafonné à 100 % pour la barre. */
const progress = (t) =>
    t.target_cents ? Math.min((t.balance_cents / t.target_cents) * 100, 100) : null;
</script>

<template>
    <AppLayout
        title="Trésorerie"
        subtitle="L'argent mis de côté : ce qui rentre dans la caisse, ce qui en sort, et pourquoi."
    >
        <template #actions>
            <Btn variant="outline" class="hidden sm:inline-flex" @click="openCaisseCreate">
                <Plus class="size-4" />
                Nouvelle caisse
            </Btn>
        </template>

        <StatTile
            label="Total mis de côté"
            :cents="props.total_cents"
            :hint="`Réparti sur ${props.treasuries.length} caisse${props.treasuries.length > 1 ? 's' : ''}`"
            class="mb-4"
        />

        <EmptyState
            v-if="props.treasuries.length === 0"
            title="Aucune caisse"
            description="Crée une caisse pour mettre de l'argent de côté : épargne, réserve TVA, projet…"
        >
            <Btn variant="solid" @click="openCaisseCreate">
                <Plus class="size-4" />
                Nouvelle caisse
            </Btn>
        </EmptyState>

        <div v-else class="grid gap-4 lg:grid-cols-2">
            <Card v-for="t in props.treasuries" :key="t.id" flush>
                <template #header>
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="mt-1 size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: t.color }"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <h2 class="truncate text-lg text-ink">{{ t.name }}</h2>
                            <p v-if="t.description" class="mt-0.5 text-xs text-ink-3">
                                {{ t.description }}
                            </p>
                        </div>
                    </div>
                </template>

                <div class="px-4 pb-4 sm:px-5 sm:pb-5">
                    <div class="flex items-end justify-between gap-3">
                        <p class="text-3xl leading-none">
                            <Money :cents="t.balance_cents" />
                        </p>
                        <div class="flex shrink-0 items-center">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
                                :aria-label="`Modifier ${t.name}`"
                                @click="openCaisseEdit(t)"
                            >
                                <Pencil class="size-4" />
                            </button>
                            <ConfirmDelete
                                :url="`${BASE}/${t.id}`"
                                :label="t.name"
                                :consequence="
                                    t.movements.length
                                        ? `Ses ${t.movements.length} mouvement(s) partiront avec elle.`
                                        : null
                                "
                            />
                        </div>
                    </div>

                    <!-- Objectif : barre + repère textuel, jamais la couleur seule -->
                    <div v-if="t.target_cents" class="mt-3">
                        <div class="mb-1.5 flex items-baseline justify-between text-xs text-ink-3">
                            <span>Objectif</span>
                            <span class="tnum">
                                {{ Math.round(progress(t)) }}% ·
                                <Money :cents="t.target_cents" compact />
                            </span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-3">
                            <div
                                class="h-full rounded-full transition-[width] duration-500"
                                :style="{ width: `${progress(t)}%`, backgroundColor: t.color }"
                            />
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <Btn variant="outline" @click="openMovement(t, 'in')">
                            <ArrowDownLeft class="size-4 text-pos" />
                            Ajouter
                        </Btn>
                        <Btn
                            variant="outline"
                            :disabled="t.balance_cents <= 0"
                            @click="openMovement(t, 'out')"
                        >
                            <ArrowUpRight class="size-4 text-neg" />
                            Retirer
                        </Btn>
                    </div>
                </div>

                <!-- Historique -->
                <div v-if="t.movements.length" class="hairline-t">
                    <p
                        class="bg-surface-2 px-4 py-2 text-[11px] font-medium tracking-wide text-ink-3 uppercase sm:px-5"
                    >
                        Historique
                    </p>
                    <ul class="max-h-64 overflow-y-auto">
                        <li
                            v-for="(m, i) in t.movements"
                            :key="m.id"
                            class="flex items-center gap-3 px-4 py-2.5 sm:px-5"
                            :class="i > 0 ? 'hairline-t' : ''"
                        >
                            <component
                                :is="m.direction === 'in' ? ArrowDownLeft : ArrowUpRight"
                                class="size-4 shrink-0"
                                :class="m.direction === 'in' ? 'text-pos' : 'text-neg'"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-ink">{{ m.label }}</span>
                                <span class="block truncate text-xs text-ink-3">
                                    {{ formatFull(m.occurred_on) }}
                                    <template v-if="m.notes"> · {{ m.notes }}</template>
                                </span>
                            </span>
                            <Money
                                :cents="m.direction === 'in' ? m.amount_cents : -m.amount_cents"
                                tone="sign"
                                signed
                                class="shrink-0 text-sm font-medium"
                            />
                            <ConfirmDelete
                                :url="`${BASE}/${t.id}/mouvements/${m.id}`"
                                :label="m.label"
                            />
                        </li>
                    </ul>
                </div>
                <p v-else class="hairline-t px-4 py-4 text-sm text-ink-3 sm:px-5">
                    Caisse vide. Ajoute de l'argent en précisant d'où il vient.
                </p>
            </Card>
        </div>

        <button
            type="button"
            class="fixed right-4 bottom-24 z-40 flex size-13 items-center justify-center rounded-full bg-ink text-page shadow-xl transition active:scale-95 sm:hidden"
            aria-label="Nouvelle caisse"
            @click="openCaisseCreate"
        >
            <Plus class="size-6" />
        </button>

        <!-- ===== Mouvement ===== -->
        <Modal
            :open="moveOpen"
            :title="isIn ? 'Ajouter à la caisse' : 'Retirer de la caisse'"
            :description="moveTarget?.name"
            @close="moveOpen = false"
        >
            <form id="move-form" class="space-y-4" @submit.prevent="submitMovement">
                <SegmentedControl
                    v-model="moveForm.direction"
                    :options="[
                        { value: 'in', label: 'Entrée' },
                        { value: 'out', label: 'Sortie' },
                    ]"
                    aria-label="Sens du mouvement"
                />

                <Field
                    v-model="moveForm.amount"
                    label="Montant"
                    type="amount"
                    :error="moveForm.errors.amount"
                    required
                />

                <Field
                    v-model="moveForm.label"
                    :label="isIn ? 'Ça vient d\'où ?' : 'Pour quelle raison ?'"
                    :placeholder="
                        isIn ? 'Prestation client, virement, prime…' : 'Achat matériel, impôts, vacances…'
                    "
                    :error="moveForm.errors.label"
                    required
                />

                <Field
                    v-model="moveForm.occurred_on"
                    label="Date"
                    type="date"
                    :error="moveForm.errors.occurred_on"
                    required
                />

                <Field
                    v-model="moveForm.notes"
                    label="Note"
                    type="textarea"
                    :rows="2"
                    placeholder="Optionnel"
                    :error="moveForm.errors.notes"
                />

                <p v-if="!isIn && moveTarget" class="text-xs text-ink-3">
                    Disponible dans cette caisse :
                    <Money :cents="moveTarget.balance_cents" />
                </p>
            </form>

            <template #footer>
                <Btn variant="ghost" @click="moveOpen = false">Annuler</Btn>
                <Btn variant="solid" type="submit" form="move-form" :disabled="moveForm.processing">
                    {{ moveForm.processing ? 'Enregistrement…' : isIn ? 'Ajouter' : 'Retirer' }}
                </Btn>
            </template>
        </Modal>

        <!-- ===== Caisse ===== -->
        <Modal
            :open="caisseOpen"
            :title="editingCaisse ? 'Modifier la caisse' : 'Nouvelle caisse'"
            @close="caisseOpen = false"
        >
            <form id="caisse-form" class="space-y-4" @submit.prevent="submitCaisse">
                <Field
                    v-model="caisseForm.name"
                    label="Nom"
                    placeholder="Épargne, Réserve TVA, Vacances…"
                    :error="caisseForm.errors.name"
                    required
                />

                <Field
                    v-model="caisseForm.description"
                    label="À quoi elle sert"
                    placeholder="Optionnel"
                    :error="caisseForm.errors.description"
                />

                <div class="grid grid-cols-2 gap-3">
                    <Field
                        v-model="caisseForm.target"
                        label="Objectif"
                        type="amount"
                        :error="caisseForm.errors.target"
                        hint="Optionnel"
                    />
                    <Field
                        v-model="caisseForm.color"
                        label="Couleur"
                        type="color"
                        :error="caisseForm.errors.color"
                    />
                </div>
            </form>

            <template #footer>
                <Btn variant="ghost" @click="caisseOpen = false">Annuler</Btn>
                <Btn
                    variant="solid"
                    type="submit"
                    form="caisse-form"
                    :disabled="caisseForm.processing"
                >
                    {{ caisseForm.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </Btn>
            </template>
        </Modal>
    </AppLayout>
</template>
