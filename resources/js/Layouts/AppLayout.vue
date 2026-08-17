<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    LayoutDashboard,
    ListTodo,
    LogOut,
    Menu,
    Moon,
    PiggyBank,
    Receipt,
    Repeat,
    Sun,
    Tags,
    X,
} from '@lucide/vue';
import { useTheme } from '@/Composables/useTheme';
import Toast from '@/Components/UI/Toast.vue';
import InstallHint from '@/Components/UI/InstallHint.vue';

defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
});

const page = usePage();
const { theme, toggle } = useTheme();
const sheetOpen = ref(false);

const nav = computed(() => page.props.nav ?? {});

/**
 * `primary` occupe la barre d'onglets mobile (zone du pouce, 4 items + Plus) ;
 * `secondary` vit dans la feuille « Plus ». Sur desktop, tout est dans la
 * colonne de gauche.
 */
const primary = computed(() => [
    { key: 'dashboard', label: 'Tableau', href: nav.value.dashboard, icon: LayoutDashboard },
    { key: 'expenses', label: 'Dépenses', href: nav.value.expenses, icon: Receipt },
    { key: 'incomes', label: 'Rentrées', href: nav.value.incomes, icon: Banknote },
    { key: 'treasuries', label: 'Caisses', href: nav.value.treasuries, icon: PiggyBank },
]);

const secondary = computed(() => [
    { key: 'subscriptions', label: 'Abonnements', href: nav.value.subscriptions, icon: Repeat },
    { key: 'tasks', label: 'À faire', href: nav.value.tasks, icon: ListTodo },
    { key: 'categories', label: 'Catégories', href: nav.value.categories, icon: Tags },
]);

const allNav = computed(() => [...primary.value, ...secondary.value]);

/** Le lien actif se déduit du chemin courant : pas de prop à câbler par page. */
function isActive(href) {
    if (!href) return false;

    const current = new URL(page.url, 'http://x').pathname;
    const target = new URL(href, 'http://x').pathname;

    return target === '/' ? current === '/' : current.startsWith(target);
}

function closeSheet() {
    sheetOpen.value = false;
}

function logout() {
    closeSheet();
    router.post(nav.value.logout);
}
</script>

<template>
    <div class="min-h-dvh bg-page">
        <!-- ============ Sidebar desktop ============ -->
        <aside
            class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-line bg-surface lg:flex"
        >
            <div class="px-5 pt-6 pb-5">
                <Link :href="nav.dashboard" class="block">
                    <!-- « Or » remonte à la surface : c'est le mot caché dans Rinor. -->
                    <span class="font-display text-2xl leading-none text-ink">
                        Rin<span class="text-gold">Or</span>
                    </span>
                    <span class="mt-1 block text-[10px] tracking-[0.14em] text-ink-3 uppercase">
                        Comptabilité
                    </span>
                </Link>
            </div>

            <nav class="flex-1 space-y-0.5 px-3">
                <Link
                    v-for="item in allNav"
                    :key="item.key"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition"
                    :class="
                        isActive(item.href)
                            ? 'bg-surface-2 font-medium text-ink'
                            : 'text-ink-2 hover:bg-surface-2 hover:text-ink'
                    "
                >
                    <component :is="item.icon" class="size-4 shrink-0" />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="space-y-0.5 border-t border-line p-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-2 transition hover:bg-surface-2 hover:text-ink"
                    @click="toggle"
                >
                    <component :is="theme === 'dark' ? Sun : Moon" class="size-4 shrink-0" />
                    {{ theme === 'dark' ? 'Thème clair' : 'Thème sombre' }}
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-2 transition hover:bg-surface-2 hover:text-ink"
                    @click="logout"
                >
                    <LogOut class="size-4 shrink-0" />
                    Se déconnecter
                </button>
            </div>
        </aside>

        <!-- ============ Barre supérieure mobile ============ -->
        <header
            class="sticky top-0 z-30 flex items-center justify-between border-b border-line bg-page/90 px-4 pt-safe pb-3 backdrop-blur lg:hidden"
        >
            <Link :href="nav.dashboard" class="font-display text-xl leading-none text-ink">
                Rin<span class="text-gold">Or</span>
            </Link>
            <button
                type="button"
                class="rounded-lg p-2 text-ink-2 transition hover:bg-surface-2"
                :aria-label="theme === 'dark' ? 'Passer au thème clair' : 'Passer au thème sombre'"
                @click="toggle"
            >
                <component :is="theme === 'dark' ? Sun : Moon" class="size-4" />
            </button>
        </header>

        <!-- ============ Contenu ============ -->
        <main class="lg:pl-60">
            <div class="mx-auto w-full max-w-6xl px-4 pt-5 pb-28 sm:px-6 lg:pt-8 lg:pb-12">
                <div
                    v-if="title"
                    class="mb-5 flex flex-wrap items-end justify-between gap-x-4 gap-y-3 lg:mb-7"
                >
                    <div class="min-w-0">
                        <h1 class="text-2xl leading-tight text-ink lg:text-[2rem]">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-1 text-sm text-ink-3">{{ subtitle }}</p>
                    </div>
                    <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                        <slot name="actions" />
                    </div>
                </div>

                <slot />
            </div>
        </main>

        <!-- ============ Onglets mobile ============ -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 backdrop-blur pb-safe lg:hidden"
            aria-label="Navigation principale"
        >
            <div class="grid grid-cols-5 pt-1.5">
                <Link
                    v-for="item in primary"
                    :key="item.key"
                    :href="item.href"
                    class="flex flex-col items-center gap-1 py-1.5 transition"
                    :class="isActive(item.href) ? 'text-ink' : 'text-ink-3'"
                >
                    <component :is="item.icon" class="size-5" />
                    <span class="text-[10px] leading-none font-medium">{{ item.label }}</span>
                </Link>

                <button
                    type="button"
                    class="flex flex-col items-center gap-1 py-1.5 transition"
                    :class="
                        sheetOpen || secondary.some((i) => isActive(i.href))
                            ? 'text-ink'
                            : 'text-ink-3'
                    "
                    @click="sheetOpen = true"
                >
                    <Menu class="size-5" />
                    <span class="text-[10px] leading-none font-medium">Plus</span>
                </button>
            </div>
        </nav>

        <!-- ============ Feuille « Plus » ============ -->
        <Teleport to="body">
            <div v-if="sheetOpen" class="fixed inset-0 z-50 flex items-end lg:hidden">
                <div class="animate-fade absolute inset-0 bg-black/45" @click="closeSheet" />

                <div
                    class="animate-sheet relative w-full rounded-t-2xl border border-line bg-surface pb-safe"
                >
                    <div class="hairline-b flex items-center justify-between px-5 py-4">
                        <span class="text-sm font-medium text-ink">Plus</span>
                        <button
                            type="button"
                            class="-mr-2 rounded-lg p-2 text-ink-3 transition hover:bg-surface-2"
                            aria-label="Fermer"
                            @click="closeSheet"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <div class="p-3">
                        <Link
                            v-for="item in secondary"
                            :key="item.key"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm transition"
                            :class="
                                isActive(item.href)
                                    ? 'bg-surface-2 font-medium text-ink'
                                    : 'text-ink-2'
                            "
                            @click="closeSheet"
                        >
                            <component :is="item.icon" class="size-4 shrink-0" />
                            {{ item.label }}
                        </Link>

                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-sm text-ink-2 transition"
                            @click="logout"
                        >
                            <LogOut class="size-4 shrink-0" />
                            Se déconnecter
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Toast />
        <InstallHint />
    </div>
</template>
