<?php

namespace App\Services\Backup;

use App\Exceptions\InvalidBackupArchive;
use ZipArchive;

/**
 * Reads and vets a backup's `manifest.json` before a restore is allowed to
 * touch the live database. Every check here is non-destructive: a failure
 * means the operator can safely pick a different file.
 *
 * Guards, in order:
 *   - the manifest exists and is a format we understand;
 *   - the database dump exists and its sha256 matches the manifest (corruption);
 *   - the backup's app version is not newer than this install;
 *   - the backup's schema is not ahead of the migrations this code ships.
 */
class ManifestValidator
{
    /**
     * @return array<string,mixed> the validated manifest
     */
    public function validate(string $workDir): array
    {
        $manifestPath = $workDir.'/manifest.json';
        if (! is_file($manifestPath)) {
            throw new InvalidBackupArchive(__('restore.errors.manifest_missing'));
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ($manifest['backup_format_version'] ?? null) !== '1') {
            throw new InvalidBackupArchive(__('restore.errors.unsupported_format'));
        }

        $dumpPath = $workDir.'/database/dump.sql';
        if (! is_file($dumpPath)) {
            throw new InvalidBackupArchive(__('restore.errors.dump_missing'));
        }

        $expected = $manifest['files']['database/dump.sql']['sha256'] ?? null;
        if ($expected && ! hash_equals((string) $expected, hash_file('sha256', $dumpPath))) {
            throw new InvalidBackupArchive(__('restore.errors.checksum_mismatch'));
        }

        if ($message = $this->compatibilityMessage($manifest)) {
            throw new InvalidBackupArchive($message);
        }

        return $manifest;
    }

    /**
     * Whether a backup's app/schema version can be restored onto this install.
     * Returns null when compatible, otherwise a ready-to-show message. Used by
     * both {@see validate()} and the wizard's review step (which only has the
     * peeked manifest, no extracted files).
     *
     * @param  array<string,mixed>  $manifest
     */
    public function compatibilityMessage(array $manifest): ?string
    {
        $current       = (string) config('pos.version', '1.0.0');
        $backupVersion = (string) ($manifest['app_version'] ?? '0');
        if (version_compare($backupVersion, $current, '>')) {
            return __('restore.errors.newer_app_version', ['backup' => $backupVersion, 'current' => $current]);
        }

        $backupSchema  = (string) ($manifest['schema_version'] ?? '');
        $currentSchema = $this->currentSchemaVersion();
        if ($backupSchema !== '' && $currentSchema !== '' && strcmp($backupSchema, $currentSchema) > 0) {
            return __('restore.errors.newer_schema');
        }

        return null;
    }

    /**
     * Peek a backup's manifest straight out of the zip without extracting the
     * whole archive — used by the wizard's "validate" step to preview what's
     * inside before the operator commits. Returns null when there's no
     * readable manifest.
     *
     * @return array<string,mixed>|null
     */
    public function peekFromZip(string $zipPath): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $json = $zip->getFromName('manifest.json');
        $zip->close();

        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * The newest migration this codebase ships — the ceiling a backup's schema
     * must not exceed. Read from the migration files, not the database, because
     * the live DB may be mid-failed-update when a rollback restore runs.
     */
    private function currentSchemaVersion(): string
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        $names = array_map(static fn ($f) => basename($f, '.php'), $files);
        sort($names);

        return (string) (end($names) ?: '');
    }
}
