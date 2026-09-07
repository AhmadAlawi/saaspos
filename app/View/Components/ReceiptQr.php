<?php

namespace App\View\Components;

use App\Services\Barcodes\QrRenderer;
use Illuminate\View\Component;

/**
 * <x-receipt-qr :url="$receiptUrl" />
 *
 * Renders a real SVG QR code of the sale's no-login public-receipt URL for
 * the printable receipt (replacing the old "[ QR ]" stub). Keeps QR
 * generation out of the Blade — resolves {@see QrRenderer} and exposes the
 * finished SVG. Renders nothing when there's no URL to encode.
 */
class ReceiptQr extends Component
{
    public string $svg;

    public function __construct(string $url, QrRenderer $renderer)
    {
        $this->svg = $renderer->svg($url);
    }

    public function shouldRender(): bool
    {
        return $this->svg !== '';
    }

    public function render(): \Illuminate\View\View
    {
        return view('components.receipt-qr');
    }
}
