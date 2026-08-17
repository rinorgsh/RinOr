<script setup>
import { onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';
import Btn from '@/Components/UI/Btn.vue';
import Field from '@/Components/UI/Field.vue';
import { useTheme } from '@/Composables/useTheme';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

// La page de connexion s'affiche avant que le layout n'existe : on s'assure
// que le thème est bien stampé même si le script inline a échoué.
const { theme } = useTheme();

onMounted(() => {
    if (!document.documentElement.dataset.theme) {
        document.documentElement.dataset.theme = theme.value;
    }
});

function submit() {
    form.post('/connexion', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Connexion" />

    <div class="relative flex min-h-dvh items-center justify-center overflow-hidden bg-page px-4 py-10">
        <!-- Trame diagonale : de la matière, pas du bruit -->
        <div class="grain pointer-events-none absolute inset-0 opacity-30" aria-hidden="true" />

        <div class="relative w-full max-w-sm">
            <div class="mb-8 text-center">
                <p class="font-display text-4xl leading-none text-ink">
                    Rin<span class="text-gold">Or</span>
                </p>
                <p class="mt-2 text-[10px] tracking-[0.18em] text-ink-3 uppercase">
                    Comptabilité
                </p>
            </div>

            <form class="rounded-xl border border-line bg-surface p-6" @submit.prevent="submit">
                <Field
                    v-model="form.email"
                    label="Adresse e-mail"
                    type="email"
                    :error="form.errors.email"
                    autofocus
                    required
                />

                <div class="mt-4">
                    <Field
                        v-model="form.password"
                        label="Mot de passe"
                        type="password"
                        :error="form.errors.password"
                        required
                    />
                </div>

                <label class="mt-4 flex cursor-pointer items-center gap-2.5">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="size-4 shrink-0 accent-[var(--ink)]"
                    />
                    <span class="text-sm text-ink-2">Rester connecté</span>
                </label>

                <Btn
                    variant="solid"
                    size="lg"
                    type="submit"
                    block
                    class="mt-6"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Connexion…' : 'Se connecter' }}
                </Btn>
            </form>

            <p class="mt-5 flex items-start gap-2 text-xs text-ink-3">
                <LockKeyhole class="mt-0.5 size-3.5 shrink-0" />
                <span>
                    Un seul compte, créé en ligne de commande. Il n'y a pas
                    d'inscription&nbsp;: rien à forcer depuis l'extérieur.
                </span>
            </p>
        </div>
    </div>
</template>
