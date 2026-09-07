<?php

namespace App\Actions\Hardware;

use App\Models\Company;
use App\Models\Terminal;
use App\Services\Hardware\EscPosCommands;
use App\Services\Hardware\PrinterConfig;

/**
 * Builds a small diagnostic test print (docs/features/hardware.md §10) —
 * a "your printer works" slip with no Sale behind it. Returns the same
 * dual-format payload shape as {@see PreparePrintPayload} so the client
 * print bridge can send it on either the WebUSB or browser-print path.
 */
class PrepareTestPrint
{
    /**
     * @return array{mode:string, paper:string, html:string, escpos_bytes:string}
     */
    public function __invoke(?Terminal $terminal = null): array
    {
        $company = Company::current() ?? new Company();
        $config  = PrinterConfig::fromTerminal($terminal, $company->receipt_paper_size ?: '80mm');

        $store = $company->display_app_name;
        $when  = format_datetime(now());
        $title = __('hardware.test.receipt_title');
        $body  = __('hardware.test.receipt_body');

        return [
            'mode'         => $config->mode,
            'paper'        => $config->paperWidth,
            'html'         => $this->html($store, $title, $body, $when),
            'escpos_bytes' => base64_encode($this->escpos($config, $store, $title, $body, $when)),
        ];
    }

    private function escpos(PrinterConfig $config, string $store, string $title, string $body, string $when): string
    {
        $E = EscPosCommands::class;

        $out = $E::INIT;
        $out .= $E::ALIGN_CENTER;
        $out .= $E::BOLD_ON.$E::SIZE_DOUBLE_HEIGHT.$store."\n".$E::SIZE_NORMAL.$E::BOLD_OFF;
        $out .= str_repeat('-', $config->charWidth)."\n";
        $out .= $E::BOLD_ON.$title."\n".$E::BOLD_OFF;
        $out .= $body."\n";
        $out .= $when."\n";
        $out .= str_repeat('-', $config->charWidth)."\n";
        $out .= $E::feed(3);
        if ($config->cutPaper) {
            $out .= $E::PARTIAL_CUT;
        }

        return $out;
    }

    private function html(string $store, string $title, string $body, string $when): string
    {
        $store = e($store);
        $title = e($title);
        $body  = e($body);
        $when  = e($when);

        return <<<HTML
            <!DOCTYPE html><html><head><meta charset="UTF-8">
            <style>
                @page { size: 80mm 120mm; margin: 4mm; }
                body { font-family: monospace; text-align: center; margin: 0; }
                .s { font-size: 15px; font-weight: 700; }
                .t { font-weight: 700; margin-top: 6px; }
                hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
            </style></head>
            <body>
                <div class="s">{$store}</div>
                <hr>
                <div class="t">{$title}</div>
                <div>{$body}</div>
                <div>{$when}</div>
                <hr>
            </body></html>
            HTML;
    }
}
