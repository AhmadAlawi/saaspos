<?php

namespace App\Actions\Settings;

use App\Models\Company;

/**
 * Persist the company profile (identity + address). Logo handling is
 * three-way like the other image fields: a new upload replaces, a
 * `logo_remove` flag clears, and absence leaves it alone — resolved in
 * the controller, which passes the final `logo_path` (or omits it).
 *
 * Hooks: `company.before_update` ($company, $data) · `company.after_update` ($company).
 */
class UpdateCompanyProfile
{
    /** @param array<string, mixed> $data */
    public function __invoke(Company $company, array $data): Company
    {
        $data = apply_filters('company.attributes', $data, $company);
        do_action('company.before_update', $company, $data);

        $company->update($data);

        do_action('company.after_update', $company);

        return $company;
    }
}
