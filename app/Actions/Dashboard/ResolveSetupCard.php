<?php

namespace App\Actions\Dashboard;

use App\Models\Company;

/**
 * Decides which state (if any) the dashboard's "Get your store ready" card
 * should render, and records the one-time celebration.
 *
 * Three outcomes:
 *   - null                      → nothing to show (dismissed, or the owner has
 *                                 already seen the congratulations).
 *   - checklist                 → still setting up; render steps + progress bar.
 *   - checklist + celebrate     → the visit that completed the last essential
 *                                 step. Shown exactly once; we flip
 *                                 `dashboard_setup_celebrated` here so the next
 *                                 dashboard load returns null forever after.
 *
 * Both flags are company-wide: once any admin dismisses (or sees the
 * celebration), the card is done for everyone.
 */
class ResolveSetupCard
{
    public function __construct(
        private readonly BuildSetupChecklist $buildChecklist,
    ) {}

    /** @return array<string, mixed>|null */
    public function __invoke(): ?array
    {
        $company = Company::current();

        if (! $company || $company->dashboard_setup_dismissed) {
            return null;
        }

        $checklist = ($this->buildChecklist)();

        if (! $checklist['complete']) {
            return $checklist;
        }

        if ($company->dashboard_setup_celebrated) {
            return null;
        }

        // Congratulate exactly once, then never again.
        $company->update(['dashboard_setup_celebrated' => true]);

        return $checklist + ['celebrate' => true];
    }
}
