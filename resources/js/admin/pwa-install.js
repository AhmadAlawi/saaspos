import { startPwa, subscribePwa, promptInstall, applyUpdate } from '../offline/pwa-installer.js';

/**
 * Topbar "Install app" / "Update" control for the admin back office.
 *
 * The Install button is ALWAYS visible (unless the app is already running
 * installed). Clicking it:
 *   - fires the browser's native install prompt when available
 *     (`beforeinstallprompt` captured by the shared pwa-installer), or
 *   - otherwise opens a small platform-aware "how to install" dialog —
 *     because Safari/iOS and Firefox never expose a programmatic prompt.
 *
 * The Update button only appears once a new service-worker version is waiting.
 *
 * Localized strings are passed in from Blade (`help`) so they stay translatable.
 */
export function pwaInstall(opts = {}) {
    const help = opts.help || {};

    return {
        installable: false,
        updateAvailable: false,
        installed: false,
        showHelp: false,
        helpTitle: help.title || 'Install app',
        helpBody: '',
        _unsub: null,

        init() {
            // Already launched as an installed app? Hide the button.
            this.installed = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                || window.navigator.standalone === true;

            this._unsub = subscribePwa((s) => {
                this.installable     = s.installable;
                this.updateAvailable = s.updateAvailable;
            });

            window.addEventListener('appinstalled', () => {
                this.installed = true;
                this.showHelp  = false;
            });

            startPwa();
        },

        destroy() {
            this._unsub?.();
        },

        async install() {
            if (this.installable) {
                const res = await promptInstall();
                // Prompt was lost / unsupported after all → show manual steps.
                if (res?.outcome === 'unavailable') this._openHelp();
                return;
            }
            this._openHelp();
        },

        _openHelp() {
            const ua = navigator.userAgent || '';
            const isIOS = /iphone|ipad|ipod/i.test(ua)
                || (/macintosh/i.test(ua) && navigator.maxTouchPoints > 1); // iPadOS reports as Mac
            const isAndroid = /android/i.test(ua);
            const isFirefox = /firefox|fxios/i.test(ua);

            if (isIOS)          this.helpBody = help.ios     || '';
            else if (isFirefox) this.helpBody = help.firefox || '';
            else if (isAndroid) this.helpBody = help.android || '';
            else                this.helpBody = help.desktop || '';

            this.showHelp = true;
        },

        update() {
            applyUpdate();
        },
    };
}
