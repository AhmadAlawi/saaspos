<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxComponent;
use App\Models\TaxGroup;
use Illuminate\View\View;

/**
 * Settings → Tax hub. Two cards: Components and Groups, each linking to
 * its CRUD page. Future Slice 2/3/4 cards (Exemptions, Reverse-charge,
 * Composition, Country reset) land here as they ship.
 */
class TaxSettingsController extends Controller
{
    public function index(): View
    {
        $this->authorize('settings.tax.view');

        return view('admin.settings.tax.index', [
            'componentCount' => TaxComponent::query()->count(),
            'groupCount'     => TaxGroup::query()->count(),
            'defaultGroup'   => TaxGroup::default(),
        ]);
    }
}
