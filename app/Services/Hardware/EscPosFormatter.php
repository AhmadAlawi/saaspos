<?php

namespace App\Services\Hardware;

use App\Models\Company;
use App\Models\ReceiptTemplate;
use App\Models\ReceiptTemplateBlock;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a completed {@see Sale} into an ESC/POS byte stream for a
 * thermal printer (docs/features/hardware.md §5.2). The layout mirrors
 * the HTML receipt (`resources/views/sales/receipt.blade.php`) — same
 * store header, item list, totals, and payments — so a customer gets
 * the same receipt whether it's printed on thermal or browser-print.
 *
 * Reads the Sale model directly (like the blade does) rather than going
 * through an intermediate DTO; honours the same `company.receipt_*`
 * toggles. Prints the logo as an ESC/POS raster (falling back to a text
 * header), the sale number as a native Code 128 barcode, and the digital
 * receipt link as a native QR — the printer rasterises both, so no image
 * bytes travel over the wire.
 *
 * When a {@see ReceiptTemplate} is resolved ({@see \App\Actions\Hardware\ResolveReceiptTemplate}),
 * `format()` loops its ordered blocks instead — see `renderBlock()`. With
 * no template (every install, day one, until an admin creates one) it
 * falls straight through to {@see formatLegacy()}, which is the exact,
 * unmodified original method body — byte-identical output guaranteed by
 * construction, not by re-deriving the same sequence a second way.
 */
class EscPosFormatter
{
    public function __construct(
        private RasterToEscPos $raster = new RasterToEscPos(),
        private ArabicTextRasterizer $arabicRaster = new ArabicTextRasterizer(),
        private CanvasReceiptRasterizer $canvasRaster = new CanvasReceiptRasterizer(),
    ) {}

    /**
     * Build the raw byte stream. The caller base64-encodes it for the
     * JSON transport to the client print bridge.
     */
    public function format(Sale $sale, PrinterConfig $config, ?Company $company = null, ?ReceiptTemplate $template = null): string
    {
        $company ??= Company::current() ?? new Company();

        if (! $template) {
            return $this->formatLegacy($sale, $config, $company);
        }

        if ($template->isCanvas()) {
            return $this->formatCanvas($sale, $config, $company, $template);
        }

        $w = $config->charWidth;
        $E = EscPosCommands::class;
        $out = $E::INIT.$E::ALIGN_LEFT;

        foreach ($template->visibleBlocks() as $block) {
            $out .= $this->renderBlock($block, $sale, $company, $config, $w);
        }

        $out .= $E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }
        if ($config->openDrawerOnCash && $this->hasCashPayment($sale)) {
            $out .= $E::kickDrawer($config->drawerPin);
        }

        return $out;
    }

    /**
     * "Canvas" layout mode — the whole receipt is one rasterized bitmap
     * (see {@see CanvasReceiptRasterizer}) since ESC/POS text mode has no
     * x/y positioning. Falls back to plain text with the sale number if
     * rasterization fails outright, so a broken canvas template never
     * produces a blank print.
     */
    private function formatCanvas(Sale $sale, PrinterConfig $config, Company $company, ReceiptTemplate $template): string
    {
        $E = EscPosCommands::class;
        $out = $E::INIT.$E::ALIGN_CENTER;

        $raster = $this->canvasRaster->render($template, $sale, $company, $config);
        $out .= $raster !== '' ? $raster."\n" : $E::BOLD_ON.$this->clean($sale->number)."\n".$E::BOLD_OFF;

        $out .= $E::ALIGN_LEFT.$E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }
        if ($config->openDrawerOnCash && $this->hasCashPayment($sale)) {
            $out .= $E::kickDrawer($config->drawerPin);
        }

        return $out;
    }

    /**
     * A refund-only receipt (docs/features/refunds.md) — plain fixed
     * sequence like {@see formatLegacy()}, no template/block system for
     * returns. Mirrors `resources/views/sales/refund-receipt.blade.php`'s
     * content exactly so the customer gets the same information whether
     * it prints on thermal or browser-print.
     *
     * A cash refund hands money back to the customer from the till —
     * opens the drawer same as a cash sale (`refunded_in_cash > 0`),
     * otherwise the cashier has no way to physically pay it out.
     */
    public function formatReturn(SaleReturn $saleReturn, PrinterConfig $config, ?Company $company = null): string
    {
        $company ??= Company::current() ?? new Company();
        $w = $config->charWidth;
        $E = EscPosCommands::class;
        $out = $E::INIT;

        $out .= $E::ALIGN_CENTER;
        if ($company->receipt_show_logo) {
            $logo = $this->logoRaster($company, $config);
            $out .= $logo !== ''
                ? $logo."\n"
                : $E::BOLD_ON.$this->clean($company->display_app_name)."\n".$E::BOLD_OFF;
        }

        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT;
        $out .= $this->clean($saleReturn->store?->name)."\n";
        $out .= $E::SIZE_NORMAL.$E::BOLD_OFF;
        foreach ($this->returnStoreAddressLines($saleReturn) as $line) {
            $out .= $this->clean($line)."\n";
        }

        // "REFUND" banner so it's unmistakable at a glance vs. a normal
        // sale receipt, matching the HTML view's rcpt-refund-banner.
        $out .= $E::BOLD_ON.'*** '.$this->en('sales.refund.receipt_banner').' ***'."\n".$E::BOLD_OFF;

        $out .= $E::ALIGN_LEFT;
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean($saleReturn->number)."\n".$E::BOLD_OFF;
        $out .= format_datetime($saleReturn->created_at)."\n";

        if ($saleReturn->sale) {
            $out .= $this->wrap($this->en('sales.refund.receipt_for_sale').': '.$this->clean($saleReturn->sale->number), $w);
        } else {
            $out .= $this->wrap($this->en('sales.refund.receipt_no_invoice'), $w);
        }
        if ($company->receipt_show_cashier && $saleReturn->cashier) {
            $out .= $this->wrap($this->en('sales.receipt.cashier').': '.$this->clean($saleReturn->cashier->name), $w);
        }
        if ($saleReturn->reason) {
            $out .= $this->wrap($this->en('sales.refund.receipt_reason').': '.$this->clean($saleReturn->reason->name), $w);
        }

        $out .= $this->rule($w);
        foreach ($saleReturn->items as $item) {
            $name = $item->name_snapshot ?: ($item->saleItem?->product_name_snapshot ?: $item->saleItem?->product?->name);
            $out .= $this->wrap($this->clean($name), $w);

            $sku = $item->sku_snapshot ?: ($item->saleItem?->sku_snapshot ?: $item->saleItem?->product?->sku);
            if ($company->receipt_show_sku && $sku) {
                $out .= $this->wrap('  '.$this->en('sales.receipt.sku').': '.$this->clean($sku), $w);
            }

            $qty = rtrim(rtrim((string) $item->quantity, '0'), '.') ?: '0';
            $out .= $this->row('  '.$qty.' x '.format_money($item->unit_price_snapshot), format_money($item->line_total), $w);

            if ($item->notes) {
                $out .= $this->wrap('  '.$this->clean($item->notes), $w);
            }
        }

        $out .= $this->rule($w);
        $out .= $this->row($this->en('sales.refund.receipt_subtotal'), format_money($saleReturn->subtotal), $w);
        if ((float) $saleReturn->tax_total > 0) {
            $out .= $this->row($this->en('sales.refund.receipt_tax'), format_money($saleReturn->tax_total), $w);
        }
        $out .= $E::BOLD_ON;
        $out .= $this->row($this->en('sales.refund.receipt_grand'), format_money($saleReturn->grand_total), $w);
        $out .= $E::BOLD_OFF;
        $out .= $this->row(
            $this->en('sales.refund.receipt_method'),
            $this->clean($saleReturn->refundMethod?->name ?: $this->en('sales.refund.receipt_method_cash')),
            $w,
        );

        $out .= $this->rule($w);
        $out .= $E::ALIGN_CENTER;
        if (trim((string) $company->receipt_footer) !== '') {
            $out .= $this->freeText($company->receipt_footer, $config)."\n";
        }

        $out .= $E::ALIGN_LEFT.$E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }
        if ($config->openDrawerOnCash && (float) ($saleReturn->refunded_in_cash ?? 0) > 0) {
            $out .= $E::kickDrawer($config->drawerPin);
        }

        return $out;
    }

    /** @return list<string> */
    private function returnStoreAddressLines(SaleReturn $saleReturn): array
    {
        $store = $saleReturn->store;
        if (! $store) {
            return [];
        }

        $lines = array_filter([
            trim((string) ($store->address_line1 ?? '')),
            trim((string) ($store->address_line2 ?? '')),
            trim(implode(', ', array_filter([
                $store->city ?? null,
                $store->state ?? null,
                $store->postal_code ?? null,
            ]))),
            trim((string) ($store->phone ?? '')),
        ]);

        return array_values($lines);
    }

    /**
     * The original, hardcoded fixed-sequence renderer — untouched since
     * before templates existed. Every install prints through this path
     * until an admin creates and assigns a {@see ReceiptTemplate}.
     */
    private function formatLegacy(Sale $sale, PrinterConfig $config, Company $company): string
    {
        $w = $config->charWidth;

        $E = EscPosCommands::class;
        $out = $E::INIT;

        /* ── Store header (centered) ───────────────────────────── */
        $out .= $E::ALIGN_CENTER;

        if ($company->receipt_show_logo) {
            // Raster the logo image when present; fall back to the app/brand
            // name in bold if there's no logo or conversion fails.
            $logo = $this->logoRaster($company, $config);
            $out .= $logo !== ''
                ? $logo."\n"
                : $E::BOLD_ON.$this->clean($company->display_app_name)."\n".$E::BOLD_OFF;
        }

        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT;
        $out .= $this->clean($sale->store?->name)."\n";
        $out .= $E::SIZE_NORMAL.$E::BOLD_OFF;

        foreach ($this->storeAddressLines($sale) as $line) {
            $out .= $this->clean($line)."\n";
        }
        if ($company->tax_registration_number) {
            $out .= $this->en('sales.receipt.gstin').': '.$this->clean($company->tax_registration_number)."\n";
        }
        if (trim((string) $company->receipt_header) !== '') {
            $out .= "\n".$this->freeText($company->receipt_header, $config)."\n";
        }

        /* ── Meta (left) ───────────────────────────────────────── */
        $out .= $E::ALIGN_LEFT;
        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean($sale->number)."\n".$E::BOLD_OFF;
        $out .= format_datetime($sale->sale_datetime ?? $sale->created_at)."\n";

        if ($company->receipt_show_customer && $sale->customer) {
            $line = $this->en('sales.receipt.customer').': '.$sale->customer->name
                .($sale->customer->phone ? ' · '.$sale->customer->phone : '');
            $out .= $this->wrap($this->clean($line), $w);
        }
        if ($company->receipt_show_cashier && $sale->cashier) {
            $out .= $this->wrap($this->en('sales.receipt.cashier').': '.$this->clean($sale->cashier->name), $w);
        }

        /* ── Items ─────────────────────────────────────────────── */
        $out .= $this->rule($w);
        foreach ($sale->items as $item) {
            $name = $item->product_name_snapshot ?: $item->product?->name;
            if ($item->variant?->label) {
                $name .= ' · '.$item->variant->label;
            }
            $out .= $this->wrap($this->clean($name), $w);

            if ($company->receipt_show_sku && ($sku = $item->sku_snapshot ?: $item->product?->sku)) {
                $out .= $this->wrap('  '.$this->en('sales.receipt.sku').': '.$this->clean($sku), $w);
            }
            if ($company->receipt_show_hsn && ($hsn = $item->hsn_snapshot ?: $item->product?->hsn_code)) {
                $out .= $this->wrap('  '.$this->en('sales.receipt.hsn').': '.$this->clean($hsn), $w);
            }

            $qty = rtrim(rtrim((string) $item->quantity, '0'), '.') ?: '0';
            $out .= $this->row(
                '  '.$qty.' x '.format_money($item->unit_price),
                format_money($item->line_total),
                $w,
            );

            if ($item->batch) {
                $note = 'Batch '.$item->batch->batch_number
                    .($item->batch->expiry_date ? ' · Exp '.$item->batch->expiry_date->toDateString() : '');
                $out .= $this->wrap('  '.$this->clean($note), $w);
            }
        }

        /* ── Totals ────────────────────────────────────────────── */
        $out .= $this->rule($w);

        // Pre-discount gross subtotal — mirror the blade's reconstruction
        // (sale stores POST-discount net + tax).
        $grossSubtotal = bcadd(
            bcadd((string) $sale->subtotal, (string) $sale->tax_total, 4),
            (string) $sale->discount_total,
            4,
        );

        $out .= $this->row($this->en('sales.receipt.subtotal'), format_money($grossSubtotal), $w);

        if ((float) $sale->discount_total > 0) {
            $out .= $this->row($this->en('sales.receipt.discount'), '-'.format_money($sale->discount_total), $w);
        }
        if ((float) $sale->tax_total > 0) {
            $out .= $this->row($this->en('sales.receipt.tax_already_included'), format_money($sale->tax_total), $w);
        }
        if ((float) ($sale->additional_charges_total ?? 0) > 0) {
            $out .= $this->row($this->en('sales.receipt.charges'), format_money($sale->additional_charges_total), $w);
        }
        if ((float) ($sale->rounding_adjustment ?? 0) !== 0.0) {
            $out .= $this->row($this->en('sales.receipt.rounding'), format_money($sale->rounding_adjustment), $w);
        }

        $out .= $E::BOLD_ON;
        $out .= $this->row($this->en('sales.receipt.grand_total'), format_money($sale->grand_total), $w);
        $out .= $E::BOLD_OFF;

        /* ── Payments ──────────────────────────────────────────── */
        foreach ($sale->payments as $payment) {
            $label = $payment->paymentMethod?->name ?? $payment->method_code;
            $out .= $this->row($this->clean($label), format_money($payment->amount), $w);

            if ((float) ($payment->tendered_amount ?? 0) > (float) $payment->amount) {
                $out .= $this->row('  '.$this->en('sales.receipt.tendered'), format_money($payment->tendered_amount), $w);
            }
        }
        if ((float) $sale->change_returned > 0) {
            $out .= $this->row($this->en('sales.receipt.change'), format_money($sale->change_returned), $w);
        }

        /* ── HSN-wise tax summary (GST invoices) ───────────────── */
        // One block per HSN code — taxable value, tax, then the
        // per-component split (CGST / SGST / …) indented beneath. Laid
        // out label-per-line rather than as columns since thermal paper
        // is too narrow for a readable HSN | taxable | tax grid. Uses
        // the same helper as the HTML receipt so the figures match.
        if ($company->receipt_show_hsn_summary) {
            $summary = \App\Support\ReceiptHsnSummary::build($sale);
            if ($summary !== []) {
                $out .= $this->rule($w);
                $out .= $E::BOLD_ON.$this->clean($this->en('sales.receipt.hsn_summary'))."\n".$E::BOLD_OFF;
                foreach ($summary as $row) {
                    $out .= $this->wrap($this->clean($this->en('sales.receipt.hsn').' '.$row['hsn']), $w);
                    $out .= $this->row('  '.$this->en('sales.receipt.hsn_col_taxable'), format_money($row['taxable']), $w);
                    $out .= $this->row('  '.$this->en('sales.receipt.hsn_col_tax'), format_money($row['tax']), $w);
                    foreach ($row['components'] as $c) {
                        $out .= $this->row('    '.$this->clean($c['name']), format_money($c['amount']), $w);
                    }
                }
            }
        }

        /* ── Footer (centered) ─────────────────────────────────── */
        $out .= $this->rule($w);
        $out .= $E::ALIGN_CENTER;

        if (trim((string) $company->receipt_footer) !== '') {
            $out .= $this->freeText($company->receipt_footer, $config)."\n";
        }
        if ($company->receipt_show_barcode) {
            // Native Code 128 of the sale number (digits print below it).
            // Falls back to plain text if the value is somehow too long.
            $bc = EscPosCommands::barcode128($this->clean($sale->number));
            $out .= $bc !== '' ? $bc : ($this->clean($sale->number)."\n");
        }
        if ($company->receipt_show_qr) {
            // Native QR of the sale's no-login public-receipt URL — the
            // customer scans it for the digital receipt. Reuses the same
            // link the HTML receipt encodes (minted once, stable on reprint).
            $qr = EscPosCommands::qr($sale->publicReceiptUrl());
            if ($qr !== '') {
                $out .= "\n".$qr.$this->clean($this->en('sales.receipt.qr_caption'))."\n";
            }
        }
        if (trim((string) $company->receipt_return_policy) !== '') {
            $out .= "\n".$this->freeText($company->receipt_return_policy, $config)."\n";
        }

        /* ── Feed, cut, drawer ─────────────────────────────────── */
        // 5 lines, not 3 — this printer's cutter sits far enough past the
        // print head that 3 sliced through the last footer line (return
        // policy text) instead of clearing it first.
        $out .= $E::feed(5);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }
        if ($config->openDrawerOnCash && $this->hasCashPayment($sale)) {
            $out .= $E::kickDrawer($config->drawerPin);
        }

        return $out;
    }

    /* ── Block-based rendering ─────────────────────────────────────── */

    /**
     * Dispatches one template block to its renderer. Every case reuses
     * the exact same helpers {@see formatLegacy()} does — this is a
     * reorganization of assembly order, not a second rendering engine.
     * Each case is self-contained re: text alignment (brackets its own
     * ALIGN_CENTER/ALIGN_LEFT) since blocks are freely reorderable and
     * can't lean on a neighbor having already set alignment, unlike the
     * fixed sequence in `formatLegacy()`.
     */
    private function renderBlock(ReceiptTemplateBlock $block, Sale $sale, Company $company, PrinterConfig $config, int $w): string
    {
        return match ($block->type) {
            'logo'        => $this->blockLogo($company, $config),
            'store_info'  => $this->blockStoreInfo($sale, $company, $w),
            'text'        => $this->blockText($block, $config),
            'items_table' => $this->blockItems($sale, $company, $w),
            'totals'      => $this->blockTotals($sale, $w),
            'payments'    => $this->blockPayments($sale, $w),
            'barcode'     => $this->blockBarcode($sale, $company),
            'qr'          => $this->blockQr($sale, $company),
            'hsn_summary' => $this->blockHsnSummary($sale, $company, $w),
            'divider'     => $this->rule($w),
            'spacer'      => "\n",
            'image'       => $this->blockImage($block, $config),
            default       => '',
        };
    }

    private function blockLogo(Company $company, PrinterConfig $config): string
    {
        if (! $company->receipt_show_logo) {
            return '';
        }
        $E = EscPosCommands::class;
        $logo = $this->logoRaster($company, $config);
        $body = $logo !== ''
            ? $logo."\n"
            : $E::BOLD_ON.$this->clean($company->display_app_name)."\n".$E::BOLD_OFF;

        return $E::ALIGN_CENTER.$body.$E::ALIGN_LEFT;
    }

    /** Store identity (centered) + sale number/date/customer/cashier (left) — one structural unit, same as the legacy "header + meta" pairing. */
    private function blockStoreInfo(Sale $sale, Company $company, int $w): string
    {
        $E = EscPosCommands::class;
        $out = $E::ALIGN_CENTER;
        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT;
        $out .= $this->clean($sale->store?->name)."\n";
        $out .= $E::SIZE_NORMAL.$E::BOLD_OFF;

        foreach ($this->storeAddressLines($sale) as $line) {
            $out .= $this->clean($line)."\n";
        }
        if ($company->tax_registration_number) {
            $out .= $this->en('sales.receipt.gstin').': '.$this->clean($company->tax_registration_number)."\n";
        }
        $out .= $E::ALIGN_LEFT;

        $out .= $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean($sale->number)."\n".$E::BOLD_OFF;
        $out .= format_datetime($sale->sale_datetime ?? $sale->created_at)."\n";

        if ($company->receipt_show_customer && $sale->customer) {
            $line = $this->en('sales.receipt.customer').': '.$sale->customer->name
                .($sale->customer->phone ? ' · '.$sale->customer->phone : '');
            $out .= $this->wrap($this->clean($line), $w);
        }
        if ($company->receipt_show_cashier && $sale->cashier) {
            $out .= $this->wrap($this->en('sales.receipt.cashier').': '.$this->clean($sale->cashier->name), $w);
        }

        return $out;
    }

    /** A custom free-text block — company-authored content, Arabic-safe via {@see freeText()}. */
    private function blockText(ReceiptTemplateBlock $block, PrinterConfig $config): string
    {
        $text = trim((string) ($block->config['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $E = EscPosCommands::class;

        return $E::ALIGN_CENTER."\n".$this->freeText($text, $config)."\n".$E::ALIGN_LEFT;
    }

    /** A custom image block — rastered exactly like the logo. */
    private function blockImage(ReceiptTemplateBlock $block, PrinterConfig $config): string
    {
        $path = (string) ($block->config['image_path'] ?? '');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return '';
        }
        $raster = $this->raster->encode(Storage::disk('public')->path($path), $this->paperRasterWidth($config));
        if ($raster === '') {
            return '';
        }
        $E = EscPosCommands::class;

        return $E::ALIGN_CENTER.$raster."\n".$E::ALIGN_LEFT;
    }

    private function blockItems(Sale $sale, Company $company, int $w): string
    {
        $out = $this->rule($w);
        foreach ($sale->items as $item) {
            $name = $item->product_name_snapshot ?: $item->product?->name;
            if ($item->variant?->label) {
                $name .= ' · '.$item->variant->label;
            }
            $out .= $this->wrap($this->clean($name), $w);

            if ($company->receipt_show_sku && ($sku = $item->sku_snapshot ?: $item->product?->sku)) {
                $out .= $this->wrap('  '.$this->en('sales.receipt.sku').': '.$this->clean($sku), $w);
            }
            if ($company->receipt_show_hsn && ($hsn = $item->hsn_snapshot ?: $item->product?->hsn_code)) {
                $out .= $this->wrap('  '.$this->en('sales.receipt.hsn').': '.$this->clean($hsn), $w);
            }

            $qty = rtrim(rtrim((string) $item->quantity, '0'), '.') ?: '0';
            $out .= $this->row(
                '  '.$qty.' x '.format_money($item->unit_price),
                format_money($item->line_total),
                $w,
            );

            if ($item->batch) {
                $note = 'Batch '.$item->batch->batch_number
                    .($item->batch->expiry_date ? ' · Exp '.$item->batch->expiry_date->toDateString() : '');
                $out .= $this->wrap('  '.$this->clean($note), $w);
            }
        }

        return $out;
    }

    private function blockTotals(Sale $sale, int $w): string
    {
        $E = EscPosCommands::class;
        $out = $this->rule($w);

        $grossSubtotal = bcadd(
            bcadd((string) $sale->subtotal, (string) $sale->tax_total, 4),
            (string) $sale->discount_total,
            4,
        );

        $out .= $this->row($this->en('sales.receipt.subtotal'), format_money($grossSubtotal), $w);

        if ((float) $sale->discount_total > 0) {
            $out .= $this->row($this->en('sales.receipt.discount'), '-'.format_money($sale->discount_total), $w);
        }
        if ((float) $sale->tax_total > 0) {
            $out .= $this->row($this->en('sales.receipt.tax_already_included'), format_money($sale->tax_total), $w);
        }
        if ((float) ($sale->additional_charges_total ?? 0) > 0) {
            $out .= $this->row($this->en('sales.receipt.charges'), format_money($sale->additional_charges_total), $w);
        }
        if ((float) ($sale->rounding_adjustment ?? 0) !== 0.0) {
            $out .= $this->row($this->en('sales.receipt.rounding'), format_money($sale->rounding_adjustment), $w);
        }

        $out .= $E::BOLD_ON;
        $out .= $this->row($this->en('sales.receipt.grand_total'), format_money($sale->grand_total), $w);
        $out .= $E::BOLD_OFF;

        return $out;
    }

    private function blockPayments(Sale $sale, int $w): string
    {
        $out = '';
        foreach ($sale->payments as $payment) {
            $label = $payment->paymentMethod?->name ?? $payment->method_code;
            $out .= $this->row($this->clean($label), format_money($payment->amount), $w);

            if ((float) ($payment->tendered_amount ?? 0) > (float) $payment->amount) {
                $out .= $this->row('  '.$this->en('sales.receipt.tendered'), format_money($payment->tendered_amount), $w);
            }
        }
        if ((float) $sale->change_returned > 0) {
            $out .= $this->row($this->en('sales.receipt.change'), format_money($sale->change_returned), $w);
        }

        return $out;
    }

    private function blockHsnSummary(Sale $sale, Company $company, int $w): string
    {
        if (! $company->receipt_show_hsn_summary) {
            return '';
        }
        $summary = \App\Support\ReceiptHsnSummary::build($sale);
        if ($summary === []) {
            return '';
        }

        $E = EscPosCommands::class;
        $out = $this->rule($w);
        $out .= $E::BOLD_ON.$this->clean($this->en('sales.receipt.hsn_summary'))."\n".$E::BOLD_OFF;
        foreach ($summary as $row) {
            $out .= $this->wrap($this->clean($this->en('sales.receipt.hsn').' '.$row['hsn']), $w);
            $out .= $this->row('  '.$this->en('sales.receipt.hsn_col_taxable'), format_money($row['taxable']), $w);
            $out .= $this->row('  '.$this->en('sales.receipt.hsn_col_tax'), format_money($row['tax']), $w);
            foreach ($row['components'] as $c) {
                $out .= $this->row('    '.$this->clean($c['name']), format_money($c['amount']), $w);
            }
        }

        return $out;
    }

    private function blockBarcode(Sale $sale, Company $company): string
    {
        if (! $company->receipt_show_barcode) {
            return '';
        }
        $E = EscPosCommands::class;
        $bc = EscPosCommands::barcode128($this->clean($sale->number));
        $body = $bc !== '' ? $bc : ($this->clean($sale->number)."\n");

        return $E::ALIGN_CENTER.$body.$E::ALIGN_LEFT;
    }

    private function blockQr(Sale $sale, Company $company): string
    {
        if (! $company->receipt_show_qr) {
            return '';
        }
        $qr = EscPosCommands::qr($sale->publicReceiptUrl());
        if ($qr === '') {
            return '';
        }
        $E = EscPosCommands::class;

        return $E::ALIGN_CENTER."\n".$qr.$this->clean($this->en('sales.receipt.qr_caption'))."\n".$E::ALIGN_LEFT;
    }

    /* ── Layout helpers ─────────────────────────────────────────── */

    /**
     * ESC/POS raster of the company logo, or '' when there's no logo or
     * GD conversion fails (the caller then prints a text header). Width is
     * capped to a modest fraction of the paper so the logo doesn't dominate.
     */
    private function logoRaster(Company $company, PrinterConfig $config): string
    {
        $path = $company->logo_path;
        if (! $path) {
            return '';
        }

        return $this->raster->encode(Storage::disk('public')->path($path), $this->paperRasterWidth($config));
    }

    /**
     * Company-configured free text (header/footer/return policy) for the
     * printed receipt. Arabic is rastered as a bitmap — see
     * {@see ArabicTextRasterizer} for why ESC/POS text mode can't print it
     * directly — with the same best-effort fallback to plain text as the
     * logo above if GD/the font/shaping isn't available.
     */
    private function freeText(?string $text, PrinterConfig $config): string
    {
        $clean = $this->clean($text);
        if ($clean === '' || ! ArabicTextRasterizer::containsArabic($clean)) {
            return $clean;
        }

        $raster = $this->arabicRaster->encode($clean, $this->paperRasterWidth($config));

        return $raster !== '' ? $raster : $clean;
    }

    private function paperRasterWidth(PrinterConfig $config): int
    {
        return $config->paperWidth === '58mm' ? 384 : 512;
    }

    /** A full-width horizontal rule of dashes. */
    private function rule(int $width): string
    {
        return str_repeat('-', max(1, $width))."\n";
    }

    /**
     * Left + right text on one line, right-aligned to `$width`. The left
     * side is truncated if the two would collide.
     */
    private function row(string $left, string $right, int $width): string
    {
        $left  = trim($left);
        $right = trim($right);

        $maxLeft = max(0, $width - $this->displayWidth($right) - 1);
        $left    = $this->truncate($left, $maxLeft);

        $gap = $width - $this->displayWidth($left) - $this->displayWidth($right);
        $gap = max(1, $gap);

        return $left.str_repeat(' ', $gap).$right."\n";
    }

    /** Word-wrap a string to the paper width, one printed line per row. */
    private function wrap(string $text, int $width): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if ($this->displayWidth($text) <= $width) {
            return $text."\n";
        }

        return rtrim(wordwrap($text, $width, "\n", true))."\n";
    }

    private function truncate(string $text, int $max): string
    {
        if ($max <= 0) {
            return '';
        }

        return $this->displayWidth($text) <= $max
            ? $text
            : mb_strimwidth($text, 0, $max, '');
    }

    private function displayWidth(string $text): int
    {
        return function_exists('mb_strwidth') ? mb_strwidth($text) : strlen($text);
    }

    /** Strip control bytes / newlines from interpolated content. */
    private function clean(?string $text): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $text) ?? '');
    }

    /**
     * Receipt labels (Subtotal, Cashier, Tendered, …) always print in
     * English, regardless of the cashier's own UI language — these are
     * plain ESC/POS text, not rasterized like {@see freeText()}, so an
     * Arabic translation here would print as codepage-mismatched garbage
     * exactly like the header/footer text did before that fix. Only the
     * company's own free-text fields carry Arabic on the printed receipt.
     */
    private function en(string $key): string
    {
        return __($key, [], 'en');
    }

    /** @return list<string> */
    private function storeAddressLines(Sale $sale): array
    {
        $store = $sale->store;
        if (! $store) {
            return [];
        }

        $lines = array_filter([
            trim((string) ($store->address_line1 ?? '')),
            trim((string) ($store->address_line2 ?? '')),
            trim(implode(', ', array_filter([
                $store->city ?? null,
                $store->state ?? null,
                $store->postal_code ?? null,
            ]))),
            trim((string) ($store->phone ?? '')),
        ]);

        return array_values($lines);
    }

    /**
     * Whether ANY payment on this sale is on a method the merchant has
     * flagged to open the drawer (`PaymentMethod::opens_cash_drawer` —
     * cash by default, but card/other methods can opt in too, e.g. when
     * card slips get filed in the same drawer). Deliberately reads the
     * per-method flag rather than hardcoding `type === 'cash'`, so this
     * stays correct as the merchant reconfigures which methods kick the
     * drawer from Settings → Payment Methods.
     */
    private function hasCashPayment(Sale $sale): bool
    {
        return $sale->payments->contains(
            fn ($p) => (bool) ($p->paymentMethod?->opens_cash_drawer ?? false)
        );
    }
}
