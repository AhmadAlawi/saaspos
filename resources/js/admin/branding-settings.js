/**
 * Alpine factory for the Branding & theme settings form.
 *
 * Holds the accent color + on-accent text color (two-way bound to the
 * swatch pickers and native color inputs) and exposes a `previewStyle`
 * that maps them onto the accent token set. Two preview panes (light /
 * dark) consume that style so the user sees how filled buttons, links,
 * toggles, and highlights look in both themes — live, before saving.
 *
 * `previewStyle` mirrors the runtime override the admin layout injects:
 * the same color-mix derivations for hover / soft / ring, so the preview
 * matches the real app once saved.
 */
export function brandingSettings(opts = {}) {
    return {
        accent: opts.accent ?? '#F97316',
        text:   opts.text   ?? '#FFFFFF',

        setAccent(hex) { this.accent = hex; },
        setText(hex)   { this.text = hex; },

        /** Accent token overrides as an inline-style string for the previews. */
        get previewStyle() {
            const a = this.accent;
            return [
                `--accent: ${a}`,
                `--accent-fg: ${this.text}`,
                `--accent-hover: color-mix(in srgb, ${a} 86%, #000)`,
                `--accent-soft: color-mix(in srgb, ${a} 12%, transparent)`,
                `--accent-ring: color-mix(in srgb, ${a} 30%, transparent)`,
            ].join('; ');
        },
    };
}
