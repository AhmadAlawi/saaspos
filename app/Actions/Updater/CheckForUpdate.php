<?php

namespace App\Actions\Updater;

use App\Models\Company;
use App\Services\Updater\UpdateClient;
use App\Services\Updater\VersionComparator;

/**
 * Checks the update feed and records the result on the company row so the
 * "update available" banner can render without re-hitting the network.
 *
 * Read-only with respect to the install — it never downloads or applies
 * anything (that's the install flow, a later slice). Runs both from the daily
 * scheduler and the manual "Check now" button.
 *
 * An unreachable feed is not an error: it's recorded as `update_last_feed_error`
 * and the app carries on (see docs/features/updater-hooks.md §2.3).
 *
 * "Available" is suppressed when the version is skipped, or the install is
 * pinned (pinning means: never prompt — manual install stays possible later).
 */
class CheckForUpdate
{
    public function __construct(
        private readonly UpdateClient $client,
        private readonly VersionComparator $comparator,
    ) {
    }

    /**
     * @return array{ok: bool, available: bool, version: ?string}
     */
    public function __invoke(): array
    {
        do_action('updater.before_check_feed');

        $company = Company::current();
        $feed    = $this->client->fetchFeed();
        $now     = now();

        if ($feed === null) {
            $company?->update([
                'update_last_checked_at' => $now,
                'update_last_feed_error' => __('updates.errors.feed_unreachable'),
            ]);
            forget_app_updates();

            return ['ok' => false, 'available' => false, 'version' => null];
        }

        do_action('updater.after_check_feed', $feed);

        $channel = $company?->update_channel ?: (string) config('pos.updater.channel', 'stable');
        $current = (string) config('pos.version', '1.0.0');
        $latest  = $feed['channels'][$channel]['latest_version'] ?? null;
        $release = ($latest && isset($feed['releases'][$latest])) ? $feed['releases'][$latest] : null;

        $skipped  = (array) ($company?->update_skipped_versions ?? []);
        $isPinned = ! empty($company?->update_pinned_version);

        $available = $latest
            && $this->comparator->isNewer((string) $latest, $current)
            && ! in_array($latest, $skipped, true)
            && ! $isPinned;

        $company?->update([
            'update_last_checked_at'   => $now,
            'update_last_feed_error'   => null,
            'update_available_version' => $available ? $latest : null,
            'update_available_release' => $available ? $release : null,
        ]);
        forget_app_updates();

        if ($available) {
            do_action('updater.update_available', $latest, $release);
        }

        return ['ok' => true, 'available' => (bool) $available, 'version' => $available ? $latest : null];
    }
}
