<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Support\InstallState;
use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    public function show()
    {
        return view('installer.welcome', [
            'currentStep' => 1,
            'languages' => $this->supportedLanguages(),
            'selectedLanguage' => InstallState::get('language', 'en'),
        ]);
    }

    public function setLanguage(Request $request)
    {
        $data = $request->validate([
            'language' => ['required', 'string', 'in:'.implode(',', array_keys($this->supportedLanguages()))],
        ]);

        InstallState::set('language', $data['language']);
        app()->setLocale($data['language']);

        return redirect()->route('install.requirements');
    }

    /** @return array<string, string> */
    private function supportedLanguages(): array
    {
        return [
            'en' => 'English',
            'ar' => 'العربية',
            'hi' => 'हिन्दी',
            'es' => 'Español',
            'fr' => 'Français',
        ];
    }
}
