<?php

namespace App\Actions\Hardware;

use App\Actions\Sales\BuildPostRefundSaleCopy;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Terminal;
use App\Services\Hardware\EscPosFormatter;
use App\Services\Hardware\PrinterConfig;
use Illuminate\Support\Facades\View;

/**
 * Builds the dual-format print payload for a sale receipt
 * (docs/features/hardware.md §5.1): HTML for the browser-print path and
 * an ESC/POS byte stream (base64) for the WebUSB path. The client print
 * bridge picks which one to use based on the terminal's capabilities;
 * the server hands it both so either path is one call away.
 *
 * Extension points:
 *   - filter `print.html`  → ($html, $sale)            augment browser-print HTML
 *   - filter `print.bytes` → ($bytes, $sale, $config)  augment ESC/POS bytes
 */
class PreparePrintPayload
{
    public function __construct(
        private EscPosFormatter $formatter,
        private ResolveReceiptTemplate $resolveTemplate = new ResolveReceiptTemplate(),
        private BuildPostRefundSaleCopy $buildCorrectedCopy = new BuildPostRefundSaleCopy(),
    ) {}

    /**
     * @return array{mode:string, paper:string, html:string, escpos_bytes:string}
     */
    public function __invoke(Sale $sale, ?Terminal $terminal = null): array
    {
        $this->loadReceiptRelations($sale);

        $company  = Company::current() ?? new Company();
        $fallback = $company->receipt_paper_size ?: '80mm';
        $config   = PrinterConfig::fromTerminal($terminal, $fallback);
        // null when no template has been created/assigned yet — both
        // renderers below fall back to their original Company-field-driven
        // rendering in that case (see ResolveReceiptTemplate's docblock).
        $template = ($this->resolveTemplate)($terminal);

        $html = View::make('sales.receipt', [
            'sale'     => $sale,
            'company'  => $company,
            'paper'    => $config->paperWidth,
            'auto'     => false,
            'template' => $template,
            // QR of the public-receipt URL, matching the ESC/POS half. The
            // formatter reuses the same link (minted once), so both formats
            // print the same code. Null when the QR toggle is off.
            'receiptUrl' => $company->receipt_show_qr ? $sale->publicReceiptUrl() : null,
        ])->render();
        $html = apply_filters('print.html', $html, $sale);

        $bytes = $this->formatter->format($sale, $config, $company, $template);
        $bytes = apply_filters('print.bytes', $bytes, $sale, $config);

        return [
            'mode'         => $config->mode,
            'paper'        => $config->paperWidth,
            'html'         => $html,
            'escpos_bytes' => base64_encode($bytes),
        ];
    }

    /**
     * Refund-only receipt — same dual-format shape as `__invoke()`, for a
     * {@see SaleReturn} instead of a {@see Sale}. A store whose terminal
     * is WebUSB-only (no OS print-queue entry for a raw thermal head) got
     * nothing at all when this came back HTML-only — `escpos_bytes` now
     * comes from {@see EscPosFormatter::formatReturn()}.
     *
     * @return array{mode:string, paper:string, html:string, escpos_bytes:string}
     */
    public function forReturn(SaleReturn $saleReturn, ?Terminal $terminal = null): array
    {
        $saleReturn->loadMissing([
            'store:id,name,code,address_line1,address_line2,city,state,postal_code,phone',
            'cashier:id,name',
            'reason:id,name',
            'refundMethod:id,name',
            'sale:id,number',
            'items.product:id,sku,name',
            'items.variant:id,sku,attributes',
            'items.saleItem:id,product_name_snapshot,sku_snapshot,product_id,variant_id',
            'items.saleItem.product:id,sku,name',
            'items.saleItem.variant:id,sku,attributes',
        ]);

        $company  = Company::current() ?? new Company();
        $fallback = $company->receipt_paper_size ?: '80mm';
        $config   = PrinterConfig::fromTerminal($terminal, $fallback);

        $html = View::make('sales.refund-receipt', [
            'saleReturn' => $saleReturn,
            'company'    => $company,
            'paper'      => $config->paperWidth,
            'auto'       => false,
        ])->render();
        $html = apply_filters('print.refund_html', $html, $saleReturn);

        $bytes = $this->formatter->formatReturn($saleReturn, $config, $company);
        $bytes = apply_filters('print.refund_bytes', $bytes, $saleReturn, $config);

        return [
            'mode'         => $config->mode,
            'paper'        => $config->paperWidth,
            'html'         => $html,
            'escpos_bytes' => base64_encode($bytes),
        ];
    }

    /**
     * The customer's "corrected" copy after a refund — same sale number
     * ({@see BuildPostRefundSaleCopy}), refunded lines dropped/reduced,
     * totals recomputed. Reuses the exact same `sales.receipt` view and
     * `EscPosFormatter::format()` call `__invoke()` uses — the in-memory
     * copy is shaped exactly like a real Sale, so every existing block/
     * canvas/legacy rendering path handles it with zero changes.
     *
     * `$originalSale` should already have `items` loaded (its real,
     * unmodified lines) — the same relation-loading `__invoke()` does.
     *
     * `has_remaining_items` is false when the refund covered the whole
     * sale — nothing left to show, so the caller should skip printing
     * this (the refund slip itself already documents a full refund;
     * printing a blank-item, zero-total "corrected copy" is just wasted
     * paper).
     *
     * @return array{mode:string, paper:string, html:string, escpos_bytes:string, has_remaining_items:bool}
     */
    public function forCorrectedCopy(Sale $originalSale, ?Terminal $terminal = null): array
    {
        $copy = ($this->buildCorrectedCopy)($originalSale);

        if ($copy->items->isEmpty()) {
            return [
                'mode' => 'browser_print', 'paper' => '80mm', 'html' => '', 'escpos_bytes' => '',
                'has_remaining_items' => false,
            ];
        }

        $company  = Company::current() ?? new Company();
        $fallback = $company->receipt_paper_size ?: '80mm';
        $config   = PrinterConfig::fromTerminal($terminal, $fallback);
        $template = ($this->resolveTemplate)($terminal);

        $html = View::make('sales.receipt', [
            'sale'       => $copy,
            'company'    => $company,
            'paper'      => $config->paperWidth,
            'auto'       => false,
            'template'   => $template,
            // The corrected copy is a print-time convenience, not a new
            // sale — no fresh public-receipt link is minted for it.
            'receiptUrl' => null,
        ])->render();
        $html = apply_filters('print.corrected_copy_html', $html, $copy, $originalSale);

        $bytes = $this->formatter->format($copy, $config, $company, $template);
        $bytes = apply_filters('print.corrected_copy_bytes', $bytes, $copy, $originalSale, $config);

        return [
            'mode'                => $config->mode,
            'paper'               => $config->paperWidth,
            'html'                => $html,
            'has_remaining_items' => true,
            'escpos_bytes'        => base64_encode($bytes),
        ];
    }

    /**
     * Eager-load exactly what the receipt blade + ESC/POS formatter read.
     * Mirrors SaleController::receipt() so both renderers see the same
     * data. Safe to call repeatedly — Eloquent skips already-loaded
     * relations.
     */
    private function loadReceiptRelations(Sale $sale): void
    {
        $sale->loadMissing([
            'store:id,name,code,address_line1,address_line2,city,state,postal_code,phone',
            'customer:id,name,phone,email',
            'cashier:id,name',
            'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'items.product:id,sku,name,hsn_code,unit_id,type',
            'items.product.unit:id,code',
            'items.product.kitItems.component:id,sku,name,unit_id',
            'items.product.kitItems.component.unit:id,code',
            'items.product.kitItems.variant:id,sku,attributes',
            'items.variant:id,sku,attributes',
            'items.batch:id,batch_number,expiry_date',
            'payments.paymentMethod:id,name,type,opens_cash_drawer',
        ]);
    }
}
