<?php

namespace App\View\Components;

use App\Services\Barcodes\BarcodeRenderer;
use Illuminate\View\Component;

/**
 * <x-receipt-barcode :value="$sale->number" />
 *
 * Renders a real Code 128 / EAN-13 SVG barcode for the printable receipt
 * (replacing the old ASCII stub). Keeps the barcode generation out of the
 * Blade — the component resolves {@see BarcodeRenderer} and exposes the
 * finished SVG.
 */
class ReceiptBarcode extends Component
{
    public string $svg;

    public function __construct(string $value, BarcodeRenderer $renderer)
    {
        $this->svg = $renderer->svg($value, 1.6, 34);
    }

    public function shouldRender(): bool
    {
        return $this->svg !== '';
    }

    public function render(): \Illuminate\View\View
    {
        return view('components.receipt-barcode');
    }
}
