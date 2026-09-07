<?php

namespace App\Http\Controllers\Installer;

use App\Actions\Installer\RunInstallerSeederStep;
use App\Actions\Installer\RunMigrationChunk;
use App\Actions\Installer\TestDatabaseConnection;
use App\Actions\Installer\WriteEnvFile;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Installer\Concerns\RendersInstallerError;
use App\Support\InstallState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DatabaseController extends Controller
{
    use RendersInstallerError;

    /** Pending migrations applied per polled request. Keeps each request short. */
    private const MIGRATIONS_PER_STEP = 10;

    public function show()
    {
        return view('installer.database', [
            'currentStep' => 4,
            'defaults' => [
                'host'     => env('DB_HOST', '127.0.0.1'),
                'port'     => (int) env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', ''),
                'username' => env('DB_USERNAME', 'root'),
            ],
        ]);
    }

    /**
     * Step 4a — test the connection and persist credentials. This is the only
     * fast, synchronous part: the heavy migrate + seed work is handed off to
     * the polled progress page (see {@see migrate()} / {@see runStep()}) so a
     * shared-hosting gateway timeout can never abort it mid-flight.
     */
    public function save(
        Request $request,
        TestDatabaseConnection $test,
        WriteEnvFile $writeEnv,
    ) {
        $data = $request->validate([
            'host'     => ['required', 'string', 'max:255'],
            'port'     => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string'],
        ]);

        $password = $data['password'] ?? '';

        // 1. Test the connection.
        $testResult = ($test)($data['host'], (int) $data['port'], $data['database'], $data['username'], $password);
        if (! $testResult['ok']) {
            $message = __('installer.database.connection_failed', ['error' => $testResult['error']]);

            // AJAX (default flow): return the error as JSON so the step can
            // surface it inline without a full-page reload.
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $message], 422);
            }

            return back()->withErrors(['host' => $message])->withInput();
        }

        // 2. Persist the credentials to .env, plus APP_URL inferred from this
        //    request so generated links / assets resolve on the real domain
        //    (incl. sub-folder installs). APP_ENV/APP_DEBUG are locked down to
        //    production only at completion — keeping debug on through the
        //    remaining steps means an install-time failure still surfaces a
        //    readable error instead of a blank 500.
        ($writeEnv)([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => $data['host'],
            'DB_PORT'       => (string) $data['port'],
            'DB_DATABASE'   => $data['database'],
            'DB_USERNAME'   => $data['username'],
            'DB_PASSWORD'   => $password,
            'APP_URL'       => rtrim(url('/'), '/'),
        ]);

        $this->applyConnection($data['host'], (int) $data['port'], $data['database'], $data['username'], $password);

        // Credentials are good; mark that and reset the migrate/seed progress so
        // a re-run of this step starts the polled flow cleanly.
        InstallState::setMany([
            'db_configured'  => true,
            'migrations_run' => false,
            'seed_index'     => 0,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'redirect' => route('install.database.migrate')]);
        }

        return redirect()->route('install.database.migrate');
    }

    /**
     * Step 4b — the progress page. JS polls {@see runStep()} until migrations
     * and seeders are done, then advances to the admin step.
     */
    public function migrate()
    {
        if (! InstallState::get('db_configured')) {
            return redirect()->route('install.database');
        }

        return view('installer.migrate', [
            'currentStep' => 4,
        ]);
    }

    /**
     * Step 4b worker — runs one bounded chunk of work and reports progress.
     * Phases: `migrate` → `seed` → `done`. Returns JSON; never throws (the
     * underlying actions trap failures so the UI can show a retry).
     */
    public function runStep(
        Request $request,
        RunMigrationChunk $migrateChunk,
        RunInstallerSeederStep $seederStep,
    ): JsonResponse {
        // Belt-and-braces: keep PHP itself from capping a slow single chunk.
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        if (! InstallState::get('db_configured')) {
            return response()->json(['ok' => false, 'error' => __('installer.error.migration_failed')], 422);
        }

        // --- Phase 1: migrations ---
        $m = ($migrateChunk)(self::MIGRATIONS_PER_STEP);
        if (! $m['ok']) {
            return response()->json([
                'ok'    => false,
                'phase' => 'migrate',
                'error' => __('installer.error.migration_failed').' '.$m['output'],
            ]);
        }

        if ($m['pending'] > 0) {
            return response()->json([
                'ok'       => true,
                'done'     => false,
                'phase'    => 'migrate',
                'progress' => $this->progress('migrate', $m['ran'], $m['total'], 0),
                'detail'   => __('installer.migrate.creating_tables', ['ran' => $m['ran'], 'total' => $m['total']]),
            ]);
        }

        // --- Phase 2: seeders ---
        $seedIndex = (int) InstallState::get('seed_index', 0);
        $seedTotal = RunInstallerSeederStep::total();

        if ($seedIndex < $seedTotal) {
            $s = ($seederStep)($seedIndex);
            if (! $s['ok']) {
                return response()->json([
                    'ok'    => false,
                    'phase' => 'seed',
                    'error' => __('installer.error.seed_failed').' '.$s['output'],
                ]);
            }

            InstallState::set('seed_index', $seedIndex + 1);

            return response()->json([
                'ok'       => true,
                'done'     => false,
                'phase'    => 'seed',
                'progress' => $this->progress('seed', $seedIndex + 1, $seedTotal, $m['total']),
                'detail'   => __('installer.migrate.seeding', ['done' => $seedIndex + 1, 'total' => $seedTotal]),
            ]);
        }

        // --- Done ---
        InstallState::setMany([
            'db_configured'  => true,
            'migrations_run' => true,
        ]);

        return response()->json([
            'ok'       => true,
            'done'     => true,
            'phase'    => 'done',
            'progress' => 100,
            'detail'   => __('installer.migrate.complete'),
            'redirect' => route('install.admin'),
        ]);
    }

    /**
     * Blend the two phases into a single 0-100 bar: migrations take the first
     * 85%, seeding the last 15%, so the bar always moves forward.
     */
    private function progress(string $phase, int $done, int $total, int $migrationTotal): int
    {
        if ($phase === 'migrate') {
            $frac = $total > 0 ? $done / $total : 1;

            return (int) round($frac * 85);
        }

        $frac = $total > 0 ? $done / $total : 1;

        return (int) round(85 + $frac * 15);
    }

    private function applyConnection(string $host, int $port, string $database, string $username, string $password): void
    {
        Config::set('database.connections.mysql.host', $host);
        Config::set('database.connections.mysql.port', $port);
        Config::set('database.connections.mysql.database', $database);
        Config::set('database.connections.mysql.username', $username);
        Config::set('database.connections.mysql.password', $password);
        DB::purge('mysql');
    }
}
