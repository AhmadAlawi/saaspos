/**
 * Republish HugeRTE's runtime assets to public/vendor/hugerte/.
 *
 * HugeRTE lazy-loads its skin CSS, icon pack, model, and individual
 * plugins from `<baseURL>/…` at runtime — it can't be bundled through
 * Vite the way a normal ESM library can. We instead serve its files
 * directly from public/ and point the editor at `/vendor/hugerte/`.
 *
 * This script copies just the bits the editor needs at runtime
 * (hugerte.min.js + the skins/themes/plugins/models/icons folders)
 * and skips package.json / license / docs.
 *
 * Hooked to `postinstall` so a fresh clone + `npm ci` gets a
 * publishable public/vendor/hugerte/ without anyone having to
 * remember an extra step.
 */

import { existsSync, mkdirSync, cpSync, rmSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const project = join(root, '..');
const src = join(project, 'node_modules', 'hugerte');
const dst = join(project, 'public', 'vendor', 'hugerte');

if (!existsSync(src)) {
    // No HugeRTE in node_modules — likely a CI step that skipped deps.
    // Silently no-op so this never breaks an install.
    process.exit(0);
}

if (existsSync(dst)) rmSync(dst, { recursive: true, force: true });
mkdirSync(dst, { recursive: true });

const keep = ['hugerte.min.js', 'icons', 'models', 'plugins', 'skins', 'themes'];
for (const entry of keep) {
    const from = join(src, entry);
    if (!existsSync(from)) continue;
    cpSync(from, join(dst, entry), { recursive: true });
}

const tally = (p) => {
    if (!existsSync(p)) return 0;
    return statSync(p).isDirectory()
        ? require('node:fs').readdirSync(p).reduce((s, e) => s + tally(join(p, e)), 0)
        : 1;
};
// eslint-disable-next-line no-console
console.log(`[publish-hugerte] HugeRTE runtime published to public/vendor/hugerte/`);
