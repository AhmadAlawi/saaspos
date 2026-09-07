<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the UI language. Self-service (no permission needed, like the
 * store switcher): sets the chosen locale in session and persists it on
 * the user so it sticks across sessions. Only active languages allowed.
 */
class LocaleController extends Controller
{
    public function switch(Request $request, string $code): RedirectResponse
    {
        $language = Language::query()->where('code', $code)->where('is_active', true)->first();
        abort_unless($language !== null, 404);

        $request->session()->put('locale', $language->code);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $language->code])->save();
        }

        return redirect()->back()->with('success', __('languages.flash.switched', ['name' => $language->native_name]));
    }
}
