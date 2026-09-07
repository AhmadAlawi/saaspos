/**
 * Global JS entry — bootstraps Alpine + the universal UI store (theme).
 * Used by every non-admin page (auth, installer, cashier, etc.). The admin
 * shell extends this via resources/js/admin.js with its own additional
 * stores and components.
 */
import Alpine from 'alpinejs';

import { registerUiStore } from './stores/ui.js';
import { registerSubmitLoader } from './lib/submit-loader.js';
import { registerHttpClient } from './lib/http.js';
import { authForm } from './auth/auth-form.js';

document.addEventListener('alpine:init', () => {
    registerUiStore(Alpine);
    registerHttpClient(Alpine);
    // AJAX submit for the forgot-password / reset-password forms.
    Alpine.data('authForm', authForm);
});

window.Alpine = Alpine;
Alpine.start();

// Every submit button shows a loader + locks against double-submit.
registerSubmitLoader();
