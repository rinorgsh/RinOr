<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Share, SquarePlus, X } from '@lucide/vue';

/**
 * Invitation à installer l'app sur l'écran d'accueil.
 *
 * Les deux plateformes ne se comportent pas pareil :
 *  - Android/Chrome émet `beforeinstallprompt`, donc on peut offrir un vrai
 *    bouton qui déclenche l'installation.
 *  - iOS n'émet rien et n'a pas d'API d'installation : le seul chemin est
 *    Partager → « Sur l'écran d'accueil ». On se contente donc d'expliquer
 *    le geste.
 *
 * Rien ne s'affiche si l'app tourne déjà en standalone.
 */
const DISMISS_KEY = 'install-hint-dismissed';

const show = ref(false);
const isIos = ref(false);
const deferredPrompt = ref(null);

function isStandalone() {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        // Propriété non standard, propre à Safari iOS.
        window.navigator.standalone === true
    );
}

function dismissed() {
    try {
        return localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
}

function onBeforeInstallPrompt(event) {
    // Empêche la mini-infobar de Chrome pour proposer l'installation au bon
    // moment, dans notre propre UI.
    event.preventDefault();
    deferredPrompt.value = event;

    if (!dismissed()) show.value = true;
}

onMounted(() => {
    if (isStandalone() || dismissed()) return;

    const ua = window.navigator.userAgent;
    isIos.value = /iPad|iPhone|iPod/.test(ua) ||
        // iPadOS 13+ se présente comme un Mac : on le distingue au tactile.
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);

    // Sur iOS, aucun événement ne viendra : on affiche directement.
    if (isIos.value) show.value = true;
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
});

async function install() {
    if (!deferredPrompt.value) return;

    deferredPrompt.value.prompt();
    await deferredPrompt.value.userChoice;

    deferredPrompt.value = null;
    close();
}

function close() {
    show.value = false;

    try {
        localStorage.setItem(DISMISS_KEY, '1');
    } catch {
        // Navigation privée : le bandeau reviendra, ce n'est pas grave.
    }
}
</script>

<template>
    <div
        v-if="show"
        class="animate-rise fixed inset-x-3 bottom-22 z-45 rounded-xl border border-line bg-surface p-3.5 shadow-xl lg:inset-x-auto lg:right-6 lg:bottom-6 lg:max-w-sm"
        role="complementary"
        aria-label="Installer l'application"
    >
        <div class="flex items-start gap-3">
            <span
                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-2"
                aria-hidden="true"
            >
                <SquarePlus class="size-4 text-gold" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-ink">Installer RinOr</p>

                <p v-if="isIos" class="mt-1 text-xs leading-relaxed text-ink-3">
                    Appuie sur
                    <Share class="mx-0.5 inline size-3 align-[-1px]" />
                    puis
                    <span class="text-ink-2">« Sur l'écran d'accueil »</span>.
                    L'app s'ouvrira sans barre d'adresse, comme une vraie app.
                </p>
                <p v-else class="mt-1 text-xs leading-relaxed text-ink-3">
                    Ajoute-la à ton écran d'accueil : elle s'ouvrira en plein
                    écran, sans barre d'adresse.
                </p>

                <button
                    v-if="deferredPrompt"
                    type="button"
                    class="mt-2.5 h-8 rounded-lg bg-ink px-3 text-xs font-medium text-page transition hover:opacity-85"
                    @click="install"
                >
                    Installer
                </button>
            </div>

            <button
                type="button"
                class="-mt-1 -mr-1 shrink-0 rounded-lg p-1.5 text-ink-3 transition hover:bg-surface-2 hover:text-ink"
                aria-label="Ne plus proposer"
                @click="close"
            >
                <X class="size-3.5" />
            </button>
        </div>
    </div>
</template>
