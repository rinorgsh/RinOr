import { readonly, ref } from 'vue';

const current = ref(
    typeof document !== 'undefined' ? document.documentElement.dataset.theme || 'light' : 'light',
);

export function useTheme() {
    function set(theme) {
        current.value = theme;
        document.documentElement.dataset.theme = theme;

        try {
            localStorage.setItem('theme', theme);
        } catch {
            // Mode navigation privée : le thème reste valable pour la session.
        }
    }

    function toggle() {
        set(current.value === 'dark' ? 'light' : 'dark');
    }

    return { theme: readonly(current), set, toggle };
}
