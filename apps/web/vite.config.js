import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * Tailwind is processed through PostCSS (postcss.config.js), not the Tailwind 4 Vite
 * plugin, because tailwind.config.js here uses the v3 JavaScript config format where the
 * design tokens live. Those tokens come straight from the UI Specification and are read by
 * both Blade and Filament, so keeping them in one JS file is worth more than the newer
 * CSS-first syntax.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],

    build: {
        // The JS budget is 100 KB gzipped on first load, enforced by scripts/check-bundle-size.js.
        // Warn well before that so an accidental heavy import is noticed while it is still
        // a one-line revert.
        chunkSizeWarningLimit: 120,
    },
});
