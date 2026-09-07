<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Updater\CheckForUpdate;
use App\Actions\Updater\InstallUpdate;
use App\Actions\Updater\RunPreflightChecks;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualUpdateRequest;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Company;
use App\Models\UpdateLog;
use App\Services\Updater\UpdatePackageReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Settings → Updates. The read-only half of the auto-updater: shows the
 * current version, lets the operator pick a channel + toggle auto-check, and
 * checks the feed on demand. Actually installing an update is a separate,
 * permission-gated flow (`updater.run`) built in a later slice.
 *
 * Gated by `updater.check`.
 */
class UpdateController extends Controller
{
    use RespondsJsonOrRedirect;

    public function index(): View
    {
        $this->authorize('updater.check');

        $company = Company::current();

        return view('admin.settings.updates', [
            'currentVersion'   => (string) config('pos.version', '1.0.0'),
            'channel'          => $company?->update_channel ?: 'stable',
            'autoCheck'        => (bool) ($company?->update_auto_check ?? true),
            'lastChecked'      => $company?->update_last_checked_at,
            'autoInstall'      => (bool) ($company?->update_auto_install ?? false),
            'pinned'           => ! empty($company?->update_pinned_version),
            'pinnedVersion'    => $company?->update_pinned_version,
            'availableVersion' => $company?->update_available_version,
            'availableRelease' => $company?->update_available_release,
            'feedError'        => $company?->update_last_feed_error,
            'channels'         => UpdateSettingsRequest::CHANNELS,
        ]);
    }

    public function check(Request $request, CheckForUpdate $check): JsonResponse
    {
        $this->authorize('updater.check');

        $result  = $check();
        $company = Company::current()?->fresh();

        $message = match (true) {
            ! $result['ok']        => __('updates.messages.feed_unreachable'),
            $result['available']   => __('updates.messages.available', ['version' => $result['version']]),
            default                => __('updates.messages.up_to_date'),
        };

        return response()->json([
            'ok'           => $result['ok'],
            'available'    => $result['available'],
            'version'      => $result['version'],
            'release'      => $company?->update_available_release,
            'last_checked' => $company?->update_last_checked_at ? format_datetime($company->update_last_checked_at) : null,
            'message'      => $message,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('updater.check');

        if (pos_is_demo()) {
            return $this->jsonOrError($request, __('settings.demo.locked'), route('admin.settings.updates.index'));
        }

        $company = Company::current();
        abort_unless($company !== null, 404);

        $pinned = $request->boolean('update_pinned');
        $data = [
            'update_channel'        => $request->input('update_channel'),
            'update_auto_check'     => $request->boolean('update_auto_check'),
            'update_auto_install'   => $request->boolean('update_auto_install'),
            'update_pinned_version' => $pinned ? (string) config('pos.version', '1.0.0') : null,
        ];

        // Pinning hides any pending prompt right away; the next check re-evaluates.
        if ($pinned) {
            $data['update_available_version'] = null;
            $data['update_available_release'] = null;
        }

        $company->update($data);
        forget_app_updates();

        return $this->jsonOrRedirect(
            $request,
            __('updates.flash.saved'),
            route('admin.settings.updates.index'),
        );
    }

    /** Skip a specific version — hides it from the banner; later versions still surface. */
    public function skip(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('updater.check');

        if (pos_is_demo()) {
            return $this->jsonOrError($request, __('settings.demo.action_locked'), route('admin.settings.updates.index'));
        }

        $company = Company::current();
        abort_unless($company !== null, 404);

        $version = (string) $request->input('version');
        if ($version !== '') {
            $skipped = (array) ($company->update_skipped_versions ?? []);
            if (! in_array($version, $skipped, true)) {
                $skipped[] = $version;
            }
            $company->update([
                'update_skipped_versions'  => $skipped,
                'update_available_version' => null,
                'update_available_release' => null,
            ]);
            forget_app_updates();
        }

        return $this->jsonOrRedirect(
            $request,
            __('updates.flash.skipped', ['version' => $version]),
            route('admin.settings.updates.index'),
        );
    }

    /** Update history — every install attempt and its outcome. */
    public function history(): View
    {
        $this->authorize('updater.check');

        return view('admin.settings.updates-history', [
            'logs' => UpdateLog::query()->orderByDesc('started_at')->limit(100)->get(),
        ]);
    }

    /** Pre-flight results for the available release — drives the install wizard. */
    public function preflight(RunPreflightChecks $preflight): JsonResponse
    {
        $this->authorize('updater.run');

        $release = Company::current()?->update_available_release;
        if (! $release || empty($release['version'])) {
            return response()->json(['message' => __('updates.errors.no_update')], 422);
        }

        $checks = $preflight($release);

        return response()->json([
            'version'     => $release['version'],
            'checks'      => array_map(fn ($c) => [
                'label'   => $c['label'],
                'passed'  => $c['passed'],
                'message' => $c['message'],
            ], $checks),
            'can_install' => $preflight->passed($checks),
        ]);
    }

    /**
     * Run the update. Synchronous — the wizard holds a progress overlay open
     * (axios timeout disabled) until this returns. InstallUpdate handles the
     * pre-update backup + rollback-on-failure internally.
     */
    public function install(Request $request, InstallUpdate $install): JsonResponse
    {
        $this->authorize('updater.run');

        if (pos_is_demo()) {
            return response()->json(['message' => __('settings.demo.action_locked')], 422);
        }

        $release = Company::current()?->update_available_release;
        if (! $release || empty($release['version'])) {
            return response()->json(['message' => __('updates.errors.no_update')], 422);
        }

        // Guard against a stale banner: the client echoes the version it saw.
        $confirm = (string) $request->input('version');
        if ($confirm !== '' && $confirm !== (string) $release['version']) {
            return response()->json(['message' => __('updates.errors.no_update')], 409);
        }

        try {
            $log = $install($release, $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'       => true,
            'version'  => $log->to_version,
            'redirect' => route('admin.settings.updates.index'),
        ]);
    }

    private const MANUAL_PREFIX = 'update-manual:';

    /**
     * Stage a manually-uploaded update package: read its update.json, confirm
     * it's newer than the current version, and return the pre-flight checks.
     * The verified local path is held server-side behind a token.
     */
    public function manualUpload(ManualUpdateRequest $request, UpdatePackageReader $reader, RunPreflightChecks $preflight): JsonResponse
    {
        $this->authorize('updater.run');

        if (pos_is_demo()) {
            return response()->json(['message' => __('settings.demo.action_locked')], 422);
        }

        $stored  = $request->file('file')->storeAs('updates/manual', Str::random(40).'.zip', 'local');
        $zipPath = Storage::disk('local')->path($stored);

        return $this->stageManualPackage($zipPath, $reader, $preflight);
    }

    /**
     * Receive one chunk of a manual package. Large update zips (with vendor/)
     * exceed the per-request size and time limits many hosts — and Cloudflare —
     * impose, so the browser slices the file and uploads it in small pieces
     * that always fit. Each chunk is appended to the assembled zip; the final
     * chunk stages the package exactly like a whole-file upload.
     */
    public function manualChunk(Request $request, UpdatePackageReader $reader, RunPreflightChecks $preflight): JsonResponse
    {
        $this->authorize('updater.run');

        if (pos_is_demo()) {
            return response()->json(['message' => __('settings.demo.action_locked')], 422);
        }

        $data = $request->validate([
            'upload_id' => ['required', 'string', 'alpha_dash', 'max:64'],
            'index'     => ['required', 'integer', 'min:0'],
            'total'     => ['required', 'integer', 'min:1', 'max:100000'],
            'file'      => ['required', 'file'],
        ]);

        $index = (int) $data['index'];
        $total = (int) $data['total'];
        $dir   = Storage::disk('local')->path('updates/manual/'.$data['upload_id']);
        $path  = $dir.'/update.zip';

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // The first chunk starts a fresh file; later chunks must find one to
        // append to (guards against out-of-order or a stale session).
        if ($index === 0) {
            @unlink($path);
        } elseif (! is_file($path)) {
            return response()->json(['message' => __('updates.errors.chunk_out_of_order')], 422);
        }

        $out = @fopen($path, 'ab');
        if ($out === false) {
            return response()->json(['message' => __('updates.errors.chunk_write_failed')], 500);
        }
        $in = @fopen($request->file('file')->getRealPath(), 'rb');
        if ($in !== false) {
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        // More chunks still coming — acknowledge and wait for the rest.
        if ($index < $total - 1) {
            return response()->json(['ok' => true, 'received' => $index + 1, 'total' => $total]);
        }

        // Final chunk: the whole zip is assembled — validate + stage it.
        return $this->stageManualPackage($path, $reader, $preflight);
    }

    /**
     * Validate an assembled manual package (read its update.json, confirm it's
     * newer, run pre-flight) and hold its verified path behind a token for the
     * install step. Shared by the whole-file and chunked upload paths.
     */
    private function stageManualPackage(string $zipPath, UpdatePackageReader $reader, RunPreflightChecks $preflight): JsonResponse
    {
        try {
            $meta = $reader->read($zipPath);
        } catch (\Throwable $e) {
            @unlink($zipPath);
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $current = (string) config('pos.version', '1.0.0');
        if (version_compare($meta['version'], $current, '<=')) {
            @unlink($zipPath);
            return response()->json([
                'message' => __('updates.errors.not_newer', ['version' => $meta['version'], 'current' => $current]),
            ], 422);
        }

        $release = [
            'version'   => $meta['version'],
            'min_php'   => $meta['min_php'],
            'min_mysql' => $meta['min_mysql'],
            'sha256'    => $meta['sha256'],
        ];
        $checks = $preflight($release);

        $token = Str::random(40);
        Cache::put(self::MANUAL_PREFIX.$token, compact('zipPath', 'release'), now()->addMinutes(30));

        return response()->json([
            'token'       => $token,
            'version'     => $meta['version'],
            'current'     => $current,
            'checks'      => array_map(fn ($c) => [
                'label'   => $c['label'],
                'passed'  => $c['passed'],
                'message' => $c['message'],
            ], $checks),
            'can_install' => $preflight->passed($checks),
        ]);
    }

    /** Install a staged manual package through the same engine as a feed install. */
    public function manualInstall(Request $request, InstallUpdate $install): JsonResponse
    {
        $this->authorize('updater.run');

        if (pos_is_demo()) {
            return response()->json(['message' => __('settings.demo.action_locked')], 422);
        }

        $context = Cache::get(self::MANUAL_PREFIX.$request->input('token'));
        if (! $context || ! is_file($context['zipPath'])) {
            return response()->json(['message' => __('updates.errors.session_expired')], 422);
        }

        try {
            $log = $install($context['release'], $request->user()?->id, null, $context['zipPath']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        @unlink($context['zipPath']);

        return response()->json([
            'ok'       => true,
            'version'  => $log->to_version,
            'redirect' => route('admin.settings.updates.index'),
        ]);
    }
}
