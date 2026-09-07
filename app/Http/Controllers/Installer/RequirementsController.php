<?php

namespace App\Http\Controllers\Installer;

use App\Actions\Installer\CheckRequirements;
use App\Http\Controllers\Controller;

class RequirementsController extends Controller
{
    public function show(CheckRequirements $check)
    {
        $result = $check();

        return view('installer.requirements', [
            'currentStep' => 2,
            'checks'      => collect($result['checks'])->groupBy('group'),
            'canContinue' => $result['can_continue'],
        ]);
    }
}
