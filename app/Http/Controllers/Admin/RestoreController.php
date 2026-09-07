<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\RunRestore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartRestoreRequest;
use App\Http\Requests\Admin\ValidateBackupRequest;
use App\Models\BackupLog;
use App\Services\Backup\ManifestValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The restore wizard — choose a backup → review → confirm → restore.
 *
 * Gated by `backup.restore` (super-admin / Admin only by default). Two thin
 * JSON endpoints back the Alpine wizard:
 *   - validate: stages a source (existing local backup or uploaded zip),
 *     peeks its manifest, and hands back a short-lived token + a summary;
 *   - store: resolves the token and runs {@see RunRestore} synchronously.
 *
 * The staged source path lives server-side in the cache (keyed by token) so
 * the client never sends or sees a filesystem path.
 */
class RestoreController extends Controller
{
    private const CACHE_PREFIX = 'restore:';

    public function index(): View
    {
        $this->authorize('backup.restore');

        $backups = BackupLog::query()
            ->successful()
            ->where('destination', 'local')
            ->whereNotNull('file_path')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (BackupLog $b) => [
                'id'         => $b->id,
                'name'       => basename((string) $b->file_path),
                'size'       => $b->file_size_bytes,
                'created_at' => $b->started_at,
                'type'       => $b->type,
                'version'    => $b->manifest['app_version'] ?? null,
            ])
            ->all();

        return view('admin.settings.restore.index', ['backups' => $backups]);
    }

    public function validateSource(ValidateBackupRequest $request, ManifestValidator $validator): JsonResponse
    {
        $this->authorize('backup.restore');

        [$path, $source, $backupId, $cleanup] = $this->resolveSource($request);

        if ($path === null || ! is_file($path)) {
            return response()->json(['message' => __('restore.errors.archive_missing')], 422);
        }

        $manifest = $validator->peekFromZip($path);
        if ($manifest === null) {
            if ($cleanup) {
                @unlink($path);
            }
            return response()->json(['message' => __('restore.errors.manifest_missing')], 422);
        }

        $incompatible = $validator->compatibilityMessage($manifest);
        $token        = Str::random(40);
        Cache::put(self::CACHE_PREFIX.$token, compact('path', 'source', 'backupId', 'cleanup'), now()->addMinutes(30));

        return response()->json([
            'token'               => $token,
            'summary'             => $this->summarize($manifest, $path),
            'compatible'          => $incompatible === null,
            'incompatible_reason' => $incompatible,
        ]);
    }

    public function store(StartRestoreRequest $request, RunRestore $runRestore): JsonResponse
    {
        $this->authorize('backup.restore');

        $context = Cache::get(self::CACHE_PREFIX.$request->input('token'));
        if (! $context || ! is_file($context['path'])) {
            return response()->json(['message' => __('restore.errors.session_expired')], 422);
        }

        $cleanup = (bool) ($context['cleanup'] ?? false);

        try {
            $runRestore(
                $context['path'],
                $context['source'] ?? 'upload',
                $request->boolean('pre_restore_backup'),
                $request->user()?->id,
                $context['backupId'] ?? null,
            );
        } catch (\Throwable $e) {
            // RunRestore has already logged + best-effort reverted. The cache
            // row is gone (the restore wipes the cache table), so just clean
            // up an uploaded temp file if there was one.
            if ($cleanup) {
                @unlink($context['path']);
            }
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($cleanup) {
            @unlink($context['path']);
        }

        // Force the operator to re-authenticate against the restored data.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'redirect' => route('login')]);
    }

    /**
     * Resolve the request to a local archive path.
     *
     * @return array{0:?string,1:string,2:?int,3:bool} [absolutePath, source, backupId, cleanupAfter]
     */
    private function resolveSource(ValidateBackupRequest $request): array
    {
        if ($request->hasFile('file')) {
            $stored = $request->file('file')->storeAs('restore-uploads', Str::random(40).'.zip', 'local');

            return [Storage::disk('local')->path($stored), 'upload', null, true];
        }

        $backup = BackupLog::query()
            ->successful()
            ->where('destination', 'local')
            ->find($request->integer('backup_id'));

        if (! $backup || ! $backup->file_path) {
            return [null, 'local', null, false];
        }

        return [Storage::disk('local')->path($backup->file_path), 'local', $backup->id, false];
    }

    /**
     * Shape the manifest into the compact summary the review step renders.
     *
     * @param  array<string,mixed>  $manifest
     * @return array<string,mixed>
     */
    private function summarize(array $manifest, string $path): array
    {
        $stats = $manifest['stats'] ?? [];

        return [
            'created_at'  => $manifest['created_at'] ?? null,
            'app_version' => $manifest['app_version'] ?? null,
            'schema'      => $manifest['schema_version'] ?? null,
            'company'     => $manifest['company']['name'] ?? null,
            'size'        => is_file($path) ? filesize($path) : null,
            'counts'      => [
                'products'  => $stats['products'] ?? null,
                'customers' => $stats['customers'] ?? null,
                'sales'     => $stats['sales'] ?? null,
                'users'     => $stats['users'] ?? null,
            ],
        ];
    }
}
