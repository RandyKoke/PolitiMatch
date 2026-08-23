import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // 'public/build' est servi directement par Laravel (service unique
            // Railway, pas de Vercel/Netlify séparé — voir cahier des charges).
            buildDirectory: 'build',
            refresh: true,
            // Direction artistique "Belgique institutionnelle sobre" : Fraunces
            // (titres/moments forts) et Manrope (corps de texte/UI). Même
            // mécanisme d'auto-hébergement au build (aucune requête externe à
            // l'exécution) ; cf. resources/css/app.css pour où chaque famille
            // est référencée (--font-display / --font-sans).
            fonts: [
                bunny('Fraunces', { weights: [600] }),
                bunny('Manrope', { weights: [400, 500, 700] }),
                // Troisième et dernière famille de l'app : réservée au texte
                // des questions du quiz, la zone de lecture la plus
                // fréquentée. Une serif de lecture (optical sizing, conçue
                // pour le texte long) plutôt que Fraunces (display, pensée
                // pour de courts titres) ou Manrope (utilitaire, sans
                // caractère).
                bunny('Newsreader', { weights: [500] }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    // Tests frontend (Vitest) : jsdom simule un DOM sans navigateur réel.
    // Config volontairement minimale — ce projet reste avant tout backend
    // Laravel/PHPUnit ; ces tests couvrent le comportement des stores Pinia
    // et le montage des vues, pas une suite e2e complète.
    test: {
        environment: 'jsdom',
        globals: true,
    },
});
