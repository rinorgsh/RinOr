<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Btn from '@/Components/UI/Btn.vue';
import Field from '@/Components/UI/Field.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/inscription', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Créer un compte" />

    <div class="relative flex min-h-dvh items-center justify-center overflow-hidden bg-page px-4 py-10">
        <div class="grain pointer-events-none absolute inset-0 opacity-30" aria-hidden="true" />

        <div class="relative w-full max-w-sm">
            <div class="mb-8 text-center">
                <p class="font-display text-4xl leading-none text-ink">
                    Rin<span class="text-gold">Or</span>
                </p>
                <p class="mt-2 text-[10px] tracking-[0.18em] text-ink-3 uppercase">
                    Créer un compte
                </p>
            </div>

            <form class="rounded-xl border border-line bg-surface p-6" @submit.prevent="submit">
                <Field
                    v-model="form.name"
                    label="Nom"
                    :error="form.errors.name"
                    autofocus
                    required
                />

                <div class="mt-4">
                    <Field
                        v-model="form.email"
                        label="Adresse e-mail"
                        type="email"
                        :error="form.errors.email"
                        required
                    />
                </div>

                <div class="mt-4">
                    <Field
                        v-model="form.password"
                        label="Mot de passe"
                        type="password"
                        :error="form.errors.password"
                        hint="12 caractères minimum."
                        required
                    />
                </div>

                <div class="mt-4">
                    <Field
                        v-model="form.password_confirmation"
                        label="Confirme le mot de passe"
                        type="password"
                        :error="form.errors.password_confirmation"
                        required
                    />
                </div>

                <Btn
                    variant="solid"
                    size="lg"
                    type="submit"
                    block
                    class="mt-6"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Création…' : 'Créer mon compte' }}
                </Btn>
            </form>

            <p class="mt-5 text-center text-sm text-ink-3">
                Déjà un compte ?
                <Link href="/connexion" class="text-ink underline underline-offset-2">
                    Se connecter
                </Link>
            </p>

            <p class="mt-4 text-center text-xs text-ink-3">
                Ton compte est actif immédiatement. Tes données ne sont visibles
                que par toi.
            </p>
        </div>
    </div>
</template>
