<script setup>
import EntryLedger from '@/Components/EntryLedger.vue';

const props = defineProps({
    entries: { type: Array, required: true },
    categories: { type: Array, required: true },
    month: { type: Object, required: true },
    total_cents: { type: Number, required: true },
});

const labels = {
    title: 'Rentrées',
    totalLabel: 'Encaissé ce mois',
    addCta: 'Ajouter',
    createTitle: 'Nouvelle rentrée',
    editTitle: 'Modifier la rentrée',
    nameLabel: 'De quoi / de qui ?',
    namePlaceholder: 'Salaire, prestation, remboursement…',
    breakdownTitle: "Ce qui rapporte le plus",
    emptyTitle: 'Aucune rentrée ce mois-ci',
    emptyDescription: "Note ce qui est entré sur le compte : salaire, prestations, remboursements.",
};
</script>

<template>
    <EntryLedger
        :entries="props.entries"
        :categories="props.categories"
        :month="props.month"
        :total-cents="props.total_cents"
        base-path="/rentrees"
        date-key="received_on"
        tone="in"
        series-color="var(--series-in)"
        :labels="labels"
    />
</template>
