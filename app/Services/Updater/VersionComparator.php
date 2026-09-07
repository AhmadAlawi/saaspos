<?php

namespace App\Services\Updater;

/**
 * Semver comparison for the updater. Thin wrapper around PHP's
 * `version_compare` (which already understands pre-release tags like
 * `1.1.0-beta.3`), plus a major/minor/patch classifier the auto-install
 * gate uses later to allow patch-only automatic updates.
 */
class VersionComparator
{
    public function isNewer(string $candidate, string $current): bool
    {
        return version_compare($candidate, $current, '>');
    }

    /**
     * Classify the jump from one version to another.
     *
     * @return 'major'|'minor'|'patch'|'none'
     */
    public function diffType(string $from, string $to): string
    {
        [$fromMajor, $fromMinor, $fromPatch] = $this->core($from);
        [$toMajor, $toMinor, $toPatch]       = $this->core($to);

        return match (true) {
            $toMajor !== $fromMajor => 'major',
            $toMinor !== $fromMinor => 'minor',
            $toPatch !== $fromPatch => 'patch',
            default                 => 'none',
        };
    }

    /**
     * The numeric MAJOR.MINOR.PATCH triple, ignoring any pre-release / build
     * suffix.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function core(string $version): array
    {
        $core    = preg_split('/[-+]/', $version)[0] ?? $version;
        $segments = array_map('intval', explode('.', $core));

        return [$segments[0] ?? 0, $segments[1] ?? 0, $segments[2] ?? 0];
    }
}
