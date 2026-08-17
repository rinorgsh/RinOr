import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // UI
                bunny('Instrument Sans', { weights: [400, 500, 600] }),
                // Titres — le contraste éditorial du serif porte l'identité
                bunny('Instrument Serif', { weights: [400] }),
                // Montants — chiffres tabulaires, alignement vertical parfait
                bunny('IBM Plex Mono', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': new URL('./resources/js', import.meta.url).pathname,
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
