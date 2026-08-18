<script setup>
import { computed, useId } from 'vue';

/**
 * Un seul composant de champ pour tout le formulaire : évite six variantes
 * quasi identiques et garantit que label, erreur et aide se comportent pareil
 * partout.
 */
const props = defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: '' },
    label: { type: String, required: true },
    type: { type: String, default: 'text' }, // text | amount | date | select | textarea | color
    error: { type: String, default: null },
    hint: { type: String, default: null },
    placeholder: { type: String, default: null },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
    rows: { type: Number, default: 3 },
    /** Pour type="select" : [{ value, label }] */
    options: { type: Array, default: () => [] },
    /** Libellé de l'option vide d'un select ; null = pas d'option vide. */
    emptyOption: { type: String, default: null },
    /**
     * Valeurs proposées en autocomplétion (input texte uniquement). Le
     * navigateur les suggère sans imposer le choix : on peut toujours saisir
     * autre chose. Sert surtout à ne pas créer deux orthographes du même nom.
     */
    suggestions: { type: Array, default: null },
});

const emit = defineEmits(['update:modelValue']);

const id = `f-${useId()}`;
const listId = `${id}-list`;
const describedBy = computed(() => {
    const ids = [];
    if (props.error) ids.push(`${id}-err`);
    else if (props.hint) ids.push(`${id}-hint`);

    return ids.length ? ids.join(' ') : undefined;
});

const control =
    'w-full rounded-lg border bg-surface px-3 text-ink transition ' +
    'placeholder:text-ink-3 focus:outline-none focus:ring-2 focus:ring-offset-0';

/*
 * Chevron du select en data-URI. Défini ici et pas dans le template : les
 * guillemets imbriqués d'un data-URI SVG cassent le parser de template Vue.
 */
const CHEVRON =
    "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23898781' stroke-width='1.5'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E\")";

const controlState = computed(() =>
    props.error
        ? 'border-neg focus:ring-neg/30'
        : 'border-line-strong focus:border-ink focus:ring-ink/15',
);

function onInput(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 flex items-baseline gap-1.5 text-xs font-medium text-ink-2">
            {{ label }}
            <span v-if="required" class="text-neg" aria-hidden="true">*</span>
        </label>

        <!-- Montant : clavier numérique sur mobile, € en suffixe, mono tabulaire -->
        <div v-if="type === 'amount'" class="relative">
            <input
                :id="id"
                :value="modelValue"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                :placeholder="placeholder ?? '0,00'"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                :autofocus="autofocus"
                class="tnum h-11 pr-9 text-right"
                :class="[control, controlState]"
                @input="onInput"
            />
            <span
                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-ink-3"
                aria-hidden="true"
            >€</span>
        </div>

        <select
            v-else-if="type === 'select'"
            :id="id"
            :value="modelValue"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            class="h-11 appearance-none bg-[length:14px] bg-[right_0.75rem_center] bg-no-repeat pr-9"
            :class="[control, controlState]"
            :style="{ backgroundImage: CHEVRON }"
            @change="onInput"
        >
            <option v-if="emptyOption !== null" value="">{{ emptyOption }}</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>

        <textarea
            v-else-if="type === 'textarea'"
            :id="id"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            class="resize-y py-2.5 leading-relaxed"
            :class="[control, controlState]"
            @input="onInput"
        />

        <!-- Couleur : pastille native + hex éditable, pour rester utilisable au clavier -->
        <div v-else-if="type === 'color'" class="flex items-center gap-2">
            <input
                :id="id"
                :value="modelValue"
                type="color"
                class="h-11 w-14 shrink-0 cursor-pointer rounded-lg border border-line-strong bg-surface p-1"
                @input="onInput"
            />
            <input
                :value="modelValue"
                type="text"
                spellcheck="false"
                :aria-label="`${label} — code hexadécimal`"
                :aria-describedby="describedBy"
                class="tnum h-11 uppercase"
                :class="[control, controlState]"
                @input="onInput"
            />
        </div>

        <template v-else>
            <input
                :id="id"
                :value="modelValue"
                :type="type"
                :placeholder="placeholder"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                :autofocus="autofocus"
                :list="suggestions?.length ? listId : undefined"
                :autocomplete="suggestions?.length ? 'off' : undefined"
                class="h-11"
                :class="[control, controlState, type === 'date' ? 'tnum' : '']"
                @input="onInput"
            />
            <datalist v-if="suggestions?.length" :id="listId">
                <option v-for="value in suggestions" :key="value" :value="value" />
            </datalist>
        </template>

        <p v-if="error" :id="`${id}-err`" class="mt-1.5 text-xs text-neg">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-1.5 text-xs text-ink-3">{{ hint }}</p>
    </div>
</template>
