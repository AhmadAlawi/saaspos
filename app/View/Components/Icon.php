<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-icon name="dashboard" class="h-5 w-5" />
 *
 * Single source of truth for our Lucide-outline icon set. All glyphs are
 * stroke-only with stroke-width 1.6 and currentColor, matching the design
 * system. Add new icons to the CATALOG array.
 */
class Icon extends Component
{
    public function __construct(public string $name) {}

    /** Returns the raw inner SVG paths for a named icon, or '' if not found. */
    public static function pathsFor(string $name): string
    {
        return self::CATALOG[$name] ?? '';
    }

    public function render(): View
    {
        return view('components.icon', [
            'paths' => self::CATALOG[$this->name] ?? '',
            'found' => isset(self::CATALOG[$this->name]),
        ]);
    }

    private const CATALOG = [
        // ── Navigation / shell ───────────────────────────────────────
        'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'box'        => '<path d="M3 7v10l9 4 9-4V7l-9-4z"/><path d="M3 7l9 4 9-4"/><path d="M12 11v10"/>',
        /* Lucide-style tag: rounded top-left, pointed top-right, diagonal
           bottom-right corner. Small filled dot punches the hole. */
        'tag'        => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 11.99V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01" stroke-width="2.4"/>',
        'truck'      => '<rect x="2" y="6" width="13" height="10" rx="1"/><path d="M15 9h5l2 4v3h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'store'      => '<path d="M3 9 4.5 4h15L21 9"/><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M3 9h18"/><path d="M9 21v-6h6v6"/>',
        'bar'        => '<path d="M3 21V9"/><path d="M9 21V5"/><path d="M15 21V12"/><path d="M21 21V8"/><path d="M3 21h18"/>',
        'pie'        => '<path d="M12 3v9l8 4a9 9 0 1 1-8-13z"/><path d="M12 3a9 9 0 0 1 9 9h-9z"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.56-1.11 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.09A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.09a1.7 1.7 0 0 0 1.56 1.03H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.56 1.03z"/>',
        'customers'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'cart'       => '<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h3l3 13h11l2-8H7"/>',
        'bell'       => '<path d="M18 16v-5a6 6 0 0 0-12 0v5l-2 2v1h16v-1z"/><path d="M10 21h4"/>',
        'menu'       => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
        'grid'       => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'calculator' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8"/><path d="M16 14v4"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/>',
        'book-open'  => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',

        // ── Common actions ───────────────────────────────────────────
        'search'     => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'eye'        => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'    => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>',
        'plus'       => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'minus'      => '<path d="M5 12h14"/>',
        'x'          => '<path d="m18 6-12 12"/><path d="m6 6 12 12"/>',
        'check'      => '<path d="M20 6 9 17l-5-5"/>',
        'check-all'  => '<polyline points="16 7 8 15 4 11"/><polyline points="20 11 13 18"/>',
        'edit'       => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
        /* Sticky-note — rectangle with a folded corner indicating
           an annotation. Used for line-item notes in the cart. */
        'note'       => '<path d="M21 15V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10z"/><path d="M15 21v-6h6"/>',
        'trash'      => '<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'archive'    => '<rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/>',
        'filter'     => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'sort'       => '<path d="m21 16-4 4-4-4"/><path d="M17 20V4"/><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/>',
        'download'   => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'upload'     => '<path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M5 3h14"/>',
        'globe'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z"/>',
        'image'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
        'camera'     => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/>',
        'maximize'   => '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'minimize'   => '<path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/>',
        'external'   => '<path d="M14 3h7v7"/><path d="M21 3l-9 9"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
        'printer'    => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
        'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'database'   => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6"/>',
        'refresh'    => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'lock'       => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'link'       => '<path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/>',
        'copy'       => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'dots'       => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
        /* Vertical kebab — per-row action menu trigger. */
        'dots-vertical' => '<circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/>',
        /* Classic drag-handle: two parallel columns of three vertical dots. */
        'grip'       => '<circle cx="9" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="15" cy="18" r="1.4"/>',
        'list'       => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'tree'       => '<path d="M21 12h-8"/><path d="M21 18h-8"/><path d="M21 6h-8"/><path d="M3 5v14"/><path d="M3 12h10"/>',

        // ── Status / feedback ────────────────────────────────────────
        'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/>',
        'alert'      => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/>',
        'trending'   => '<path d="M3 17 9 11l4 4 8-9"/><path d="M21 6h-6"/><path d="M21 6v6"/>',
        'trending-d' => '<path d="M3 7 9 13l4-4 8 9"/><path d="M21 18h-6"/><path d="M21 18v-6"/>',

        // ── Arrows + chevrons ────────────────────────────────────────
        'chevron'        => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right'  => '<path d="m9 18 6-6-6-6"/>',
        'back'           => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'arrow-right'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',

        // ── Theme toggle ─────────────────────────────────────────────
        'sun'   => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.93 19.07 1.41-1.41"/><path d="m17.66 6.34 1.41-1.41"/>',
        'moon'  => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        /* Auto = split sun / moon: circle outline + vertical divider, with
           the right half filled to indicate "follows OS preference".
           Matches design-system/admin/shell.js (profileDropdown). */
        'theme-auto' => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',

        // ── User / identity ──────────────────────────────────────────
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'logout' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'star'   => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z"/>',

        // ── Sidebar toggle pair ──────────────────────────────────────
        'panel-left-close' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path d="m15 9-3 3 3 3"/>',
        'panel-left-open'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path d="m13 9 3 3-3 3"/>',

        // ── POS-specific ─────────────────────────────────────────────
        'pos'       => '<rect x="3" y="3" width="18" height="14" rx="2"/><path d="M3 9h18"/><path d="M7 13h4"/><circle cx="16" cy="13" r="1"/><path d="M8 21h8"/><path d="M12 17v4"/>',
        'card'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'qr-code'   => '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><path d="M15 15h2v2h-2z"/><path d="M19 15h2"/><path d="M15 19v2"/><path d="M19 19h2v2h-2z"/>',
        'cash'      => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 10v.01"/><path d="M18 14v.01"/>',
        'receipt'   => '<path d="M4 22V2l2 2 2-2 2 2 2-2 2 2 2-2 2 2 2-2v20l-2-2-2 2-2-2-2 2-2-2-2 2-2-2-2 2z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/>',
        /* Barcode — alternating stroked bars + caption mark. Matches the
           Lucide "barcode" glyph closely enough for the scan input. */
        'barcode'   => '<path d="M3 5v14"/><path d="M7 5v14"/><path d="M11 5v14"/><path d="M14 5v14"/><path d="M18 5v14"/><path d="M21 5v14"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        /* Counter-clockwise rotating arrow — the universal "undo /
           refund / return" glyph (matches Lucide `rotate-ccw`). */
        'refund'    => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M10.85 12.15 19 4"/><path d="m18 5 2 2"/><path d="m15 8 2 2"/>',
    ];
}
