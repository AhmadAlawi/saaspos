/**
 * Rich-text editor — HugeRTE wrapper used by the privacy / terms editor
 * in Settings → Privacy & terms.
 *
 * HugeRTE is a fork of TinyMCE; it ships a self-contained UI (toolbar,
 * dialogs, dropdowns) so the factory is mostly init + writing changes
 * to a hidden form input. Compared to TipTap, it's a heavier bundle
 * (~500 KB served as a static asset, not bundled through Vite) but it
 * doesn't fight Alpine's reactivity and the toolbar "just works".
 *
 * Loading model:
 *   - The HugeRTE runtime is published to /vendor/hugerte/ by the
 *     scripts/publish-hugerte.mjs postinstall hook.
 *   - We lazy-load the script the first time a richTextEditor mounts
 *     so the legal-pages screen is the only place that pays the cost;
 *     every other admin page stays untouched.
 *
 * Usage from a Blade component (see resources/views/components/admin/rich-text-editor.blade.php):
 *   <div x-data="richTextEditor({ initial: '...' })">
 *       <textarea x-ref="ta" class="rte-textarea">…</textarea>
 *       <input type="hidden" name="{{ name }}" x-ref="hidden">
 *   </div>
 */

const HUGERTE_BASE   = '/vendor/hugerte';
const HUGERTE_SCRIPT = `${HUGERTE_BASE}/hugerte.min.js`;

/** Promise that resolves once HugeRTE's global is on `window`. Cached
 *  so concurrent editor mounts share the same load. */
let _loadPromise = null;

function loadHugeRTE() {
    if (window.hugerte) return Promise.resolve(window.hugerte);
    if (_loadPromise) return _loadPromise;

    _loadPromise = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = HUGERTE_SCRIPT;
        s.async = true;
        s.referrerPolicy = 'origin';
        // Bypass Cloudflare Rocket Loader — it defers `<script>` tags
        // to load after page ready, which mis-orders HugeRTE's setup
        // and breaks `hugerte.init()` lookup.
        s.setAttribute('data-cfasync', 'false');
        s.addEventListener('load',  () => resolve(window.hugerte));
        s.addEventListener('error', () => reject(new Error(`Failed to load ${HUGERTE_SCRIPT}`)));
        document.head.appendChild(s);
    });
    return _loadPromise;
}

/**
 * Hide the editor instance from Alpine's reactivity. Keeping it on
 * `this.editor` would let Alpine wrap it in a Proxy, and HugeRTE's
 * internal event flow does object-identity comparisons that break the
 * moment a fresh proxy wrapper gets returned. Same defence we used
 * for TipTap — works here too.
 */
const _editors = new WeakMap();

export function richTextEditor(opts = {}) {
    const initial = (opts.initial ?? '').toString();

    return {
        ready: false,

        get editor() {
            return _editors.get(this) ?? null;
        },

        async init() {
            // Seed the textarea + hidden input synchronously so a form
            // submit before HugeRTE finishes loading still posts the
            // initial value rather than an empty string.
            this.$refs.ta.value     = initial;
            this.$refs.hidden.value = initial;

            // Defer init until the textarea is actually rendered.
            // The legal-pages screen mounts BOTH editors at page load
            // but only one is visible at a time (x-show toggles
            // display:none on the parent pane). HugeRTE can't size an
            // editor whose target has zero box dimensions, so we wait
            // for the first time the textarea becomes visible.
            await this._whenVisible();

            let hugerte;
            try {
                hugerte = await loadHugeRTE();
            } catch (e) {
                // Library failed to fetch (network / 404). The bare
                // textarea is already visible (graceful degradation),
                // so the user can still edit + save plain HTML.
                // eslint-disable-next-line no-console
                console.warn('[rich-text-editor] HugeRTE failed to load; falling back to textarea', e);
                return;
            }

            const result = await hugerte.init({
                target:                 this.$refs.ta,
                base_url:               HUGERTE_BASE,
                license_key:            'gpl',
                menubar:                false,
                statusbar:              false,
                branding:               false,
                promotion:              false,
                resize:                 true,
                height:                 460,
                content_style:          'body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 14.5px; line-height: 1.55; padding: 14px 18px; }',
                plugins:                'lists link autolink',
                toolbar:                'undo redo | blocks | bold italic strikethrough | bullist numlist | blockquote | link unlink | removeformat',
                block_formats:          'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3',
                link_default_target:    '_blank',
                link_assume_external_targets: true,
                link_title:             false,
                target_list:            false,
                rel_list:               [{ title: 'No-follow + new tab', value: 'noopener nofollow' }],
                default_link_target:    '_blank',
                paste_as_text:          false,
                // Honour the host theme. Light vs dark is picked via the
                // `<html class="dark">` toggle the rest of the admin
                // already uses; HugeRTE has matching oxide / oxide-dark
                // skins.
                skin:                   document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',
                content_css:            document.documentElement.classList.contains('dark') ? 'dark'       : 'default',
                setup: (editor) => {
                    // Each editor closes over its own hidden input and
                    // flushes the current HTML on every interaction.
                    const hidden = this.$refs.hidden;
                    const sync   = () => { hidden.value = editor.getContent(); };

                    editor.on('input keyup change blur SetContent undo redo', sync);

                    // Belt-and-braces: also force-sync at form-submit
                    // time, in capture phase, so we run BEFORE the
                    // global data-ajax-form handler builds FormData.
                    // Otherwise the very last keystroke can race and
                    // the server gets a stale (or empty) value — that's
                    // the symptom that caused /privacy-policy to 404
                    // after the user thought they'd saved content.
                    const form = this.$refs.ta.closest('form');
                    if (form) {
                        form.addEventListener('submit', sync, true);
                    }
                },
            });

            // hugerte.init() returns an array of created editor instances.
            const editor = Array.isArray(result) ? result[0] : result;
            if (!editor) return;

            _editors.set(this, editor);
            this.ready = true;
            // Final seed in case `setup`'s Change listener missed the
            // initial setContent (HugeRTE silently swallows pre-init
            // SetContent events on some browsers).
            this.$refs.hidden.value = editor.getContent();
        },

        destroy() {
            const editor = _editors.get(this);
            if (editor) editor.destroy();
            _editors.delete(this);
        },

        /**
         * Resolve once the textarea actually occupies pixels in the
         * viewport. Returns immediately when the element is already
         * visible (the active pane). For the hidden pane, an
         * IntersectionObserver fires when the cashier clicks the tab
         * and Alpine's x-show drops display:none — we then run init.
         *
         * Falls back to an immediate resolve when IO isn't available
         * (very old browsers); HugeRTE's measure-then-layout pass can
         * sometimes recover later anyway.
         */
        _whenVisible() {
            const el = this.$refs.ta;
            if (!el) return Promise.resolve();
            if (el.offsetParent !== null) return Promise.resolve();
            if (typeof IntersectionObserver !== 'function') return Promise.resolve();
            return new Promise((resolve) => {
                const io = new IntersectionObserver((entries) => {
                    if (entries.some((e) => e.isIntersecting)) {
                        io.disconnect();
                        resolve();
                    }
                });
                io.observe(el);
            });
        },
    };
}
