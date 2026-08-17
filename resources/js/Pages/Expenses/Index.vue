<script setup>
import EntryLedger from '@/Components/EntryLedger.vue';

const props = defineProps({
    entries: { type: Array, required: true },
    categories: { type: Array, required: true },
    month: { type: Object, required: true },
    total_cents: { type: Number, required: true },
});

const labels = {
    title: 'Dépenses',
    totalLabel: 'Dépensé ce mois',
    addCta: 'Ajouter',
    createTitle: 'Nouvelle dépense',
    editTitle: 'Modifier la dépense',
    nameLabel: 'Quoi ?',
    namePlaceholder: 'Courses, essence, resto…',
    breakdownTitle: 'Où part mon argent',
    emptyTitle: 'Aucune dépense ce mois-ci',
    emptyDescription:
        'Ajoute ce que tu as dépensé. Les abonnements sont ailleurs : ils se comptent tout seuls.',
};
</script>

<template>
    <EntryLedger
        :entries="props.entries"
        :categories="props.categories"
        :month="props.month"
        :total-cents="props.total_cents"
        base-path="/depenses"
        date-key="spent_on"
        tone="out"
        series-color="var(--series-out)"
        :labels="labels"
    />
</template>
