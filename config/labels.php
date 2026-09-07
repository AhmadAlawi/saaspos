<?php

/**
 * Label layouts for the product label printer (docs/features/hardware.md §6.3).
 * Dimensions are in mm and drive a CSS grid on the printable sheet.
 *
 * Two families, distinguished by `type`:
 *
 *   - `sheet` — Avery-style grids printed to an A4 inkjet / laser printer.
 *               `cols × rows` labels per physical page, offset by the sheet's
 *               unprintable margins.
 *
 *   - `roll`  — continuous label rolls on a thermal label printer, which is
 *               what most shops actually use. Each label is its OWN page, so
 *               `cols`/`rows` are 1 and the `@page` size becomes the label
 *               itself rather than A4. Margins/gaps are zero — the printer
 *               feeds exactly one label at a time.
 *
 * `per_page` is derived as cols × rows; the renderer paginates labels into
 * pages of that size (so a roll layout emits one label per page).
 */

return [
    'default' => 'roll-60x40',

    'layouts' => [

        /* ── A4 sheets (inkjet / laser) ───────────────────────────── */

        'a4-24' => [
            'type'       => 'sheet',
            'label'      => '24 per sheet — 63.5 × 33.9 mm',
            'cols'       => 3,
            'rows'       => 8,
            'label_w_mm' => 63.5,
            'label_h_mm' => 33.9,
            'margin_top_mm'  => 12.9,
            'margin_left_mm' => 7.75,
            'col_gap_mm' => 2.5,
            'row_gap_mm' => 0,
        ],
        'a4-30' => [
            'type'       => 'sheet',
            'label'      => '30 per sheet — 63.5 × 25.4 mm',
            'cols'       => 3,
            'rows'       => 10,
            'label_w_mm' => 63.5,
            'label_h_mm' => 25.4,
            'margin_top_mm'  => 21.5,
            'margin_left_mm' => 7.75,
            'col_gap_mm' => 2.5,
            'row_gap_mm' => 0,
        ],
        'a4-40' => [
            'type'       => 'sheet',
            'label'      => '40 per sheet — 45.7 × 25.4 mm',
            'cols'       => 4,
            'rows'       => 10,
            'label_w_mm' => 45.7,
            'label_h_mm' => 25.4,
            'margin_top_mm'  => 21.5,
            'margin_left_mm' => 9.8,
            'col_gap_mm' => 2.5,
            'row_gap_mm' => 0,
        ],

        /* ── Label rolls (thermal) — one label per page ───────────── */

        'roll-30x20' => [
            'type'       => 'roll',
            'label'      => 'Roll — 30 × 20 mm',
            'cols'       => 1,
            'rows'       => 1,
            'label_w_mm' => 30,
            'label_h_mm' => 20,
            'margin_top_mm'  => 0,
            'margin_left_mm' => 0,
            'col_gap_mm' => 0,
            'row_gap_mm' => 0,
        ],
        'roll-38x25' => [
            'type'       => 'roll',
            'label'      => 'Roll — 38 × 25 mm',
            'cols'       => 1,
            'rows'       => 1,
            'label_w_mm' => 38,
            'label_h_mm' => 25,
            'margin_top_mm'  => 0,
            'margin_left_mm' => 0,
            'col_gap_mm' => 0,
            'row_gap_mm' => 0,
        ],
        'roll-40x30' => [
            'type'       => 'roll',
            'label'      => 'Small label — 40 × 30 mm (gap)',
            'cols'       => 1,
            'rows'       => 1,
            'label_w_mm' => 40,
            'label_h_mm' => 30,
            'margin_top_mm'  => 0,
            'margin_left_mm' => 0,
            'col_gap_mm' => 0,
            'row_gap_mm' => 0,
        ],
        'roll-50x25' => [
            'type'       => 'roll',
            'label'      => 'Roll — 50 × 25 mm',
            'cols'       => 1,
            'rows'       => 1,
            'label_w_mm' => 50,
            'label_h_mm' => 25,
            'margin_top_mm'  => 0,
            'margin_left_mm' => 0,
            'col_gap_mm' => 0,
            'row_gap_mm' => 0,
        ],
        'roll-58x40' => [
            'type'       => 'roll',
            'label'      => 'Roll — 58 × 40 mm',
            'cols'       => 1,
            'rows'       => 1,
            'label_w_mm' => 58,
            'label_h_mm' => 40,
            'margin_top_mm'  => 0,
            'margin_left_mm' => 0,
            'col_gap_mm' => 0,
            'row_gap_mm' => 0,
        ],
        'roll-60x40' => [
            'type'       => 'roll',
            // Continuous stock with a black timing mark on the liner back
            // (vs. the gap-sensed die-cut stock the other roll sizes use).
            // The sensor mode itself is a printer-driver setting, not
            // something this page controls — only the label geometry
            // (the @page size) matters here.
            'label'      => 'Shelf label — 60 × 40 mm (continuous, black mark)',
            'cols'       => 1,
            'rows'       => 1,
            'label_w_mm' => 60,
            'label_h_mm' => 40,
            'margin_top_mm'  => 0,
            'margin_left_mm' => 0,
            'col_gap_mm' => 0,
            'row_gap_mm' => 0,
        ],
        'roll-100x50' => [
            'type'       => 'roll',
            'label'      => 'Roll — 100 × 50 mm',
            'cols'       => 1,
            'rows'       => 1,
            'label_w_mm' => 100,
            'label_h_mm' => 50,
            'margin_top_mm'  => 0,
            'margin_left_mm' => 0,
            'col_gap_mm' => 0,
            'row_gap_mm' => 0,
        ],
    ],
];
