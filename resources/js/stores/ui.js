/**
 * Global UI store — theme (light / dark / auto), persisted to localStorage.
 * Used by both the admin shell and standalone pages (auth, installer, cashier).
 */
export function registerUiStore(Alpine) {
    Alpine.store('ui', {
        theme: 'light',  // 'light' | 'dark' | 'auto'

        init() {
            // The user's saved choice wins; otherwise fall back to the
            // company default (pos-theme-default meta), then 'light'.
            const fallback = document.querySelector('meta[name="pos-theme-default"]')?.content || 'light';
            try {
                this.theme = localStorage.getItem('pos_theme') || fallback;
            } catch (e) { this.theme = fallback; }
            this.apply();
        },

        set(value) {
            this.theme = value;
            try { localStorage.setItem('pos_theme', value); } catch (e) {}
            this.apply();
        },

        toggle() {
            this.set(this.theme === 'dark' ? 'light' : 'dark');
        },

        apply() {
            const wantsDark = this.theme === 'dark'
                || (this.theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', wantsDark);
        },
    });
}
