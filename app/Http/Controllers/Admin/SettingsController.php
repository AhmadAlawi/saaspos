<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Master settings page — a grid of cards, one per settings group. Each
 * card links to that group's detail page. Add a new group by appending
 * one row to the `$groups` array in resources/views/admin/settings/index.blade.php.
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.index');
    }
}
