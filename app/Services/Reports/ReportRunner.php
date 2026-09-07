<?php

namespace App\Services\Reports;

use App\Support\ReportPeriod;
use App\Support\ReportRegistry;
use Carbon\CarbonImmutable;

/**
 * Runs a registered report headlessly — no HTTP request, no authenticated
 * user — and writes the result to a file. This is what lets a scheduled
 * report (or any background job) produce the exact same output a user would
 * download from the report screen.
 *
 * Each report's Query service + Export action are named in
 * {@see ReportRegistry}; the runner resolves the stored parameters
 * (period / from / to / store_id / as_of) into concrete arguments, runs the
 * query, asks the export action to `build()` the header + rows, then renders
 * a file via {@see ReportFileRenderer}.
 */
class ReportRunner
{
    public function __construct(private ReportFileRenderer $renderer) {}

    /**
     * @param  array<string, mixed>  $params   stored report parameters
     * @param  string                $format   csv | xlsx | pdf
     * @param  ?string               $timezone tz to resolve relative periods in (defaults to app tz)
     * @return array{path: string, filename: string, row_count: int, title: string}
     */
    public function run(string $reportKey, array $params, string $format, ?string $timezone = null): array
    {
        $meta = ReportRegistry::get($reportKey);
        if (! $meta) {
            throw new \InvalidArgumentException("Unknown report key [{$reportKey}].");
        }

        $params  = ReportRegistry::sanitizeParams($params);
        $storeId = isset($params['store_id']) && $params['store_id'] !== '' ? (int) $params['store_id'] : null;

        $query  = app($meta['query']);
        $export = app($meta['export']);

        if (($meta['date_mode'] ?? 'range') === 'as_of') {
            $asOf              = $this->resolveAsOf($params, $timezone);
            $result            = $query($asOf, $storeId);
            $fromLabel         = $toLabel = $asOf->toDateString();
        } else {
            [$from, $to] = $this->resolveRange($params, $timezone);
            $result      = $query($from, $to, $storeId);
            $fromLabel   = $from->toDateString();
            $toLabel     = $to->toDateString();
        }

        [$header, $rows] = $export->build($result);

        $path = $this->renderer->render(
            str_replace('_', '-', $reportKey),
            __($meta['title_key']),
            $header,
            $rows,
            $format,
            $fromLabel,
            $toLabel,
            method_exists($export, 'moneyColumns') ? $export->moneyColumns() : [],
        );

        return [
            'path'      => $path,
            'filename'  => basename($path),
            'row_count' => count($rows),
            'title'     => __($meta['title_key']),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(array $params, ?string $tz): array
    {
        $preset = (string) ($params['period'] ?? '');
        if ($preset === '') {
            $preset = (isset($params['from']) || isset($params['to'])) ? 'custom' : 'this_month';
        }

        $range = ReportPeriod::resolve($preset, $tz);
        if ($range !== null) {
            return $range;
        }

        $now  = CarbonImmutable::now($tz);
        $from = ($this->parseDate($params['from'] ?? null, $tz) ?? $now->startOfMonth())->startOfDay();
        $to   = ($this->parseDate($params['to'] ?? null, $tz) ?? $now)->endOfDay();
        if ($from->isAfter($to)) {
            $from = $to->startOfMonth();
        }

        return [$from, $to];
    }

    /** @param array<string, mixed> $params */
    private function resolveAsOf(array $params, ?string $tz): CarbonImmutable
    {
        return ($this->parseDate($params['as_of'] ?? null, $tz)
            ?? CarbonImmutable::now($tz))->startOfDay();
    }

    private function parseDate(?string $raw, ?string $tz): ?CarbonImmutable
    {
        if (! $raw) {
            return null;
        }

        try {
            return $tz ? CarbonImmutable::parse($raw, $tz) : CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
