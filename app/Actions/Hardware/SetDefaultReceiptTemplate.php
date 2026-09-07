<?php

namespace App\Actions\Hardware;

use App\Models\ReceiptTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Make a template the fallback used when neither a terminal nor its
 * store has an explicit assignment ({@see ResolveReceiptTemplate}).
 * Setting one clears the flag on every other template, same pattern as
 * {@see \App\Actions\Stores\SetDefaultStore}.
 */
class SetDefaultReceiptTemplate
{
    public function __invoke(ReceiptTemplate $template): ReceiptTemplate
    {
        DB::transaction(function () use ($template) {
            ReceiptTemplate::query()
                ->where('is_default', true)
                ->whereKeyNot($template->getKey())
                ->update(['is_default' => false]);

            $template->forceFill(['is_default' => true, 'is_active' => true])->save();
        });

        return $template;
    }
}
