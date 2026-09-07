<?php

namespace App\Actions\Installer;

/**
 * Runs a bounded slice of the pending migrations and reports progress.
 *
 * The installer cannot run all migrations in one HTTP request: on shared
 * hosting the web server's gateway timeout (Apache `Timeout`, FastCGI
 * `fastcgi_read_timeout`, or a fronting proxy) kills the request long before
 * PHP's `max_execution_time` matters. So the database step polls this action
 * a chunk at a time, keeping every request short. Already-run migrations are
 * skipped, so the process is naturally idempotent and resumable.
 */
class RunMigrationChunk
{
    /**
     * Run up to $limit pending migrations.
     *
     * @return array{ok: bool, ran: int, total: int, pending: int, output: string}
     */
    public function __invoke(int $limit = 10): array
    {
        try {
            /** @var \Illuminate\Database\Migrations\Migrator $migrator */
            $migrator = app('migrator');
            $migrator->setConnection(config('database.default'));

            // The migrations table itself is created on the first chunk.
            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran   = $migrator->getRepository()->getRan();

            // getMigrationFiles() is keyed by migration name and already sorted,
            // so slicing the leftovers preserves run order across chunks.
            $pending = [];
            foreach ($files as $name => $path) {
                if (! in_array($name, $ran, true)) {
                    $pending[] = $path;
                }
            }

            $total = count($files);

            if ($pending === []) {
                return ['ok' => true, 'ran' => count($ran), 'total' => $total, 'pending' => 0, 'output' => ''];
            }

            $chunk = array_slice($pending, 0, max(1, $limit));
            $migrator->requireFiles($chunk);
            $migrator->runPending($chunk);

            $ranAfter = count($migrator->getRepository()->getRan());

            return [
                'ok'      => true,
                'ran'     => $ranAfter,
                'total'   => $total,
                'pending' => max(0, $total - $ranAfter),
                'output'  => '',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ran' => 0, 'total' => 0, 'pending' => 0, 'output' => $e->getMessage()];
        }
    }
}
