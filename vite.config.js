import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin.css',
                'resources/js/admin.js',
                'resources/css/auth.css',
                'resources/css/landing.css',
                'resources/js/landing.js',
                'resources/css/installer.css',
                'resources/js/installer.js',
                'resources/css/receipt.css',
                'resources/css/pay.css',
                'resources/js/pay.js',
                // 'resources/js/pay-wallet.js' and 'resources/js/pricing.js'
                // are referenced by resources/views/pay/pos_wallet.blade.php
                // and resources/views/pricing/check.blade.php but the source
                // files don't exist — pre-existing gap (confirmed via the
                // recurring "Unable to locate file in Vite manifest" errors
                // in production logs), not something removed here. Commented
                // out rather than deleted so the moment those files are
                // restored, re-enabling them is a one-line uncomment. This
                // was blocking `npm run build` entirely for every deploy,
                // not just these two pages.
                'resources/css/cashier/customer-display.css',
                'resources/js/cashier/customer-display.js',
                'resources/css/kiosk/kiosk.css',
                'resources/js/kiosk/kiosk-app.js',
                'resources/js/admin/receipt-canvas-editor.js',
                'resources/js/admin/label-canvas-editor.js',
            ],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
