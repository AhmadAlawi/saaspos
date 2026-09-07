<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\EmailBackupCopy;
use App\Actions\Settings\PruneBackups;
use App\Actions\Settings\RunBackup;
use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackupSettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backup settings — automatic backup cadence + listing / downloading /
 * deleting the actual `.zip` files. Stored on the company row; consumed
 * by the scheduler (`routes/console.php`) and the on-demand "Run now"
 * button on this page. Gated by `settings.view` / `settings.update`.
 */
class BackupSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        $company = Company::current() ?? new Company();
        $disk    = $this->disk($company->backup_target_disk ?: 'local');

        $files = collect($disk->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(fn ($f) => [
                'name'         => basename($f),
                'size'         => $disk->size($f),
                'modified_at'  => $disk->lastModified($f),
            ])
            ->sortByDesc('modified_at')
            ->values()
            ->all();

        return view('admin.settings.backup', [
            'company'     => $company,
            'frequencies' => BackupSettingsRequest::FREQUENCIES,
            'disks'       => $this->availableDisks(),
            'files'       => $files,
        ]);
    }

    public function update(BackupSettingsRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.backup.edit'),
            );
        }

        $company = Company::current();
        abort_unless($company !== null, 404);

        $update($company, $request->backupData());
        forget_app_backup();

        return $this->jsonOrRedirect(
            $request,
            __('settings.backup.flash.updated'),
            route('admin.settings.backup.edit'),
        );
    }

    public function runNow(Request $request, RunBackup $run, PruneBackups $prune, EmailBackupCopy $emailCopy): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.backup.edit'),
            );
        }

        try {
            $log = $run('manual', $request->user()?->id);
            $prune();
            $emailCopy($log);
        } catch (\Throwable $e) {
            return $this->jsonOrError(
                $request,
                __('settings.backup.flash.run_failed', ['error' => $e->getMessage()]),
                route('admin.settings.backup.edit'),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('settings.backup.flash.created', ['name' => basename((string) $log->file_path)]),
            route('admin.settings.backup.edit'),
        );
    }

    public function download(string $filename): StreamedResponse|RedirectResponse
    {
        $this->authorize('settings.view');

        // A backup archive is the entire database — never let it be pulled
        // off the public demo.
        if (pos_is_demo()) {
            return redirect()->route('admin.settings.backup.edit')
                ->with('error', __('settings.demo.action_locked'));
        }

        $this->guardFilename($filename);

        $company = Company::current();
        $disk    = $this->disk($company?->backup_target_disk ?: 'local');
        $path    = 'backups/'.$filename;

        abort_unless($disk->exists($path), 404);

        return $disk->download($path);
    }

    public function destroy(Request $request, string $filename): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.backup.edit'),
            );
        }

        $this->guardFilename($filename);

        $company = Company::current();
        $disk    = $this->disk($company?->backup_target_disk ?: 'local');
        $path    = 'backups/'.$filename;

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        return $this->jsonOrRedirect(
            $request,
            __('settings.backup.flash.deleted', ['name' => $filename]),
            route('admin.settings.backup.edit'),
        );
    }

    private function disk(string $name): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(array_key_exists($name, config('filesystems.disks', [])) ? $name : 'local');
    }

    /**
     * Disks offered in the target-disk dropdown. Constrained to the
     * `BackupSettingsRequest::ALLOWED_DISKS` whitelist so we don't list
     * backends the backup action isn't wired up to (e.g. S3 — roadmap,
     * not built yet).
     *
     * @return array<string, string>
     */
    private function availableDisks(): array
    {
        $configured = config('filesystems.disks', []);
        $out = [];
        foreach (BackupSettingsRequest::ALLOWED_DISKS as $name) {
            if (array_key_exists($name, $configured)) {
                $out[$name] = Str::headline($name);
            }
        }
        return $out;
    }

    /** Reject anything other than a plain backup filename — no traversal. */
    private function guardFilename(string $filename): void
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+\.zip$/', $filename) === 1, 404);
    }
}
