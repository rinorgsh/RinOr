<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Circle, CircleDot, ListTodo, Repeat, TriangleAlert } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Money from '@/Components/UI/Money.vue';
import MonthNav from '@/Components/UI/MonthNav.vue';
import StatTile from '@/Components/UI/StatTile.vue';
import BarList from '@/Components/Charts/BarList.vue';
import TrendBars from '@/Components/Charts/TrendBars.vue';
import { relativeDays } from '@/Composables/useDates';

const props = defineProps({
    report: { type: Object, required: true },
});

const r = computed(() => props.report);
const t = computed(() => props.report.totals);

const monthLabel = computed(() => {
    const l = r.value.month.label ?? '';

    return l.charAt(0).toUpperCase() + l.slice(1);
});

const outflowHint = computed(() => {
    const parts = [];
    if (t.value.expense_cents) parts.push('dépenses saisies');
    if (t.value.fixed_cents) parts.push('charge fixe');

    return parts.length ? parts.join(' + ') : 'rien ce mois-ci';
});

const netHint = computed(() =>
    t.value.savings_rate === null
        ? 'aucune rentrée enregistrée'
        : `${t.value.savings_rate}% des rentrées conservé`,
);

/** Le mois est-il totalement vide ? Sert à proposer un point de départ. */
const isEmpty = computed(
    () => t.value.income_cents === 0 && t.value.expense_cents === 0,
);

const openTasks = computed(() => r.value.tasks.todo + r.value.tasks.doing);
</script>

<template>
    <AppLayout title="Où part mon or" :subtitle="monthLabel">
        <template #actions>
            <MonthNav :month="r.month" base-path="/" />
        </template>

        <!-- ================= Chiffres du mois ================= -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <StatTile
                label="Rentrées"
                :cents="t.income_cents"
                tone="in"
                accent="var(--series-in)"
                :hint="`en ${r.month.label}`"
            />
            <StatTile
                label="Sorties"
                :cents="t.outflow_cents"
                tone="out"
                accent="var(--series-out)"
                :hint="outflowHint"
            />
            <StatTile
                label="Solde du mois"
                :cents="t.net_cents"
                tone="sign"
                signed
                :hint="netHint"
            />
            <StatTile
                label="Mis de côté"
                :cents="t.treasury_cents"
                hint="total des caisses"
            />
        </div>

        <!-- Détail des sorties : dépenses saisies vs abonnements -->
        <div class="mt-3 grid grid-cols-2 gap-3 lg:gap-4">
            <div class="rounded-xl border border-line bg-surface px-4 py-3">
                <p class="text-[11px] tracking-wide text-ink-3 uppercase">Dépenses saisies</p>
                <p class="mt-1 text-lg leading-none">
                    <Money :cents="t.expense_cents" tone="out" />
                </p>
            </div>
            <Link
                href="/abonnements"
                class="rounded-xl border border-line bg-surface px-4 py-3 transition hover:border-line-strong"
            >
                <p class="flex items-center gap-1.5 text-[11px] tracking-wide text-ink-3 uppercase">
                    Abonnements
                    <Repeat class="size-3" />
                </p>
                <p class="mt-1 text-lg leading-none">
                    <Money :cents="t.fixed_cents" tone="out" />
                </p>
                <!-- Ce montant est le total annuel divisé par 12, pas la somme
                     des prélèvements du mois : il faut le dire. -->
                <p class="mt-1 text-[10px] text-ink-3">lissés sur 12 mois</p>
            </Link>
        </div>

        <!-- ================= Tendance + échéances ================= -->
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <Card
                class="lg:col-span-2"
                title="Rentrées et sorties"
                subtitle="Les 6 mois qui précèdent, charge fixe incluse"
            >
                <TrendBars
                    :trend="r.trend"
                    color-in="var(--series-in)"
                    color-out="var(--series-out)"
                />
            </Card>

            <Card title="Prochains prélèvements">
                <ul v-if="r.upcoming_subscriptions.length" class="space-y-3">
                    <li
                        v-for="sub in r.upcoming_subscriptions"
                        :key="sub.id"
                        class="flex items-baseline justify-between gap-3"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm text-ink">{{ sub.name }}</span>
                            <span
                                class="flex items-center gap-1 text-xs"
                                :class="sub.days_left < 0 ? 'font-medium text-neg' : 'text-ink-3'"
                            >
                                <TriangleAlert v-if="sub.days_left < 0" class="size-3" />
                                {{ relativeDays(sub.next_due_on) }}
                            </span>
                        </span>
                        <Money :cents="sub.amount_cents" class="shrink-0 text-sm" />
                    </li>
                </ul>
                <p v-else class="text-sm text-ink-3">
                    Aucune échéance renseignée. Ajoute une date sur tes abonnements pour les voir
                    arriver.
                </p>
            </Card>
        </div>

        <!-- ================= D'où vient / où va l'argent ================= -->
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <Card
                title="Dépenses par catégorie"
                subtitle="Là où le mois se joue vraiment"
            >
                <BarList
                    v-if="r.expense_by_category.length"
                    :items="r.expense_by_category"
                    color="var(--series-out)"
                    :max="7"
                    show-dots
                />
                <p v-else class="text-sm text-ink-3">
                    Aucune dépense enregistrée en {{ r.month.label }}.
                </p>
            </Card>

            <Card
                title="Mes plus grosses dépenses"
                subtitle="Par libellé, cumulées sur le mois"
            >
                <BarList
                    v-if="r.top_expenses.length"
                    :items="r.top_expenses"
                    color="var(--series-out)"
                    :max="6"
                />
                <p v-else class="text-sm text-ink-3">Rien à afficher pour ce mois.</p>
            </Card>

            <Card
                title="Rentrées par catégorie"
                subtitle="Ce qui rapporte le plus"
            >
                <BarList
                    v-if="r.income_by_category.length"
                    :items="r.income_by_category"
                    color="var(--series-in)"
                    :max="7"
                    show-dots
                />
                <p v-else class="text-sm text-ink-3">
                    Aucune rentrée enregistrée en {{ r.month.label }}.
                </p>
            </Card>

            <Card
                title="Mes meilleures sources"
                subtitle="Par libellé, cumulées sur le mois"
            >
                <BarList
                    v-if="r.top_income_sources.length"
                    :items="r.top_income_sources"
                    color="var(--series-in)"
                    :max="6"
                />
                <p v-else class="text-sm text-ink-3">Rien à afficher pour ce mois.</p>
            </Card>
        </div>

        <!-- ================= À faire ================= -->
        <Card class="mt-4" title="À faire">
            <template #action>
                <Link
                    href="/a-faire"
                    class="flex items-center gap-1 text-xs text-ink-3 transition hover:text-ink"
                >
                    Tout voir
                    <ArrowRight class="size-3.5" />
                </Link>
            </template>

            <div
                v-if="r.tasks.overdue > 0"
                class="mb-3 flex items-center gap-2 rounded-lg bg-st-doing-soft px-3 py-2"
            >
                <TriangleAlert class="size-4 shrink-0 text-st-doing" />
                <p class="text-sm text-ink">
                    {{ r.tasks.overdue }} tâche{{ r.tasks.overdue > 1 ? 's' : '' }} en retard
                </p>
            </div>

            <ul v-if="r.tasks.next.length" class="space-y-2.5">
                <li
                    v-for="task in r.tasks.next"
                    :key="task.id"
                    class="flex items-baseline gap-2.5"
                >
                    <component
                        :is="task.priority === 'high' ? CircleDot : Circle"
                        class="mt-0.5 size-3.5 shrink-0"
                        :class="task.priority === 'high' ? 'text-st-high' : 'text-ink-3'"
                    />
                    <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ task.title }}</span>
                    <span
                        v-if="task.due_on"
                        class="shrink-0 text-xs"
                        :class="task.is_overdue ? 'font-medium text-neg' : 'text-ink-3'"
                    >{{ relativeDays(task.due_on) }}</span>
                </li>
            </ul>
            <p v-else class="flex items-center gap-2 text-sm text-ink-3">
                <ListTodo class="size-4" />
                Rien en attente.
            </p>

            <p v-if="openTasks > r.tasks.next.length" class="mt-3 text-xs text-ink-3">
                + {{ openTasks - r.tasks.next.length }} autre(s) en cours
            </p>
        </Card>

        <!-- Amorce quand le mois est vierge -->
        <div
            v-if="isEmpty"
            class="mt-4 rounded-xl border border-dashed border-line px-5 py-5"
        >
            <p class="font-display text-lg text-ink">Ce mois est encore vide</p>
            <p class="mt-1 text-sm text-ink-3">
                Ta charge fixe est déjà comptée (<Money :cents="t.fixed_cents" /> d'abonnements).
                Ajoute tes dépenses et tes rentrées pour que le tableau se remplisse.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Link
                    href="/depenses"
                    class="inline-flex h-10 items-center rounded-lg bg-ink px-3.5 text-sm font-medium text-page transition hover:opacity-85"
                >
                    Ajouter une dépense
                </Link>
                <Link
                    href="/rentrees"
                    class="inline-flex h-10 items-center rounded-lg border border-line-strong px-3.5 text-sm font-medium text-ink transition hover:bg-surface-2"
                >
                    Ajouter une rentrée
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
