<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CorpusSyncService;
use Illuminate\Console\Command;

final class CorpusSyncCommand extends Command
{
    protected $signature = 'corpus:sync';

    protected $description = 'Sync all active sources: hash-diff their documents and dispatch index/delete jobs.';

    public function handle(CorpusSyncService $corpusSyncService): int
    {
        $result = $corpusSyncService->sync();

        if ($result['locked']) {
            $this->warn('corpus:sync läuft bereits (Lock aktiv) - dieser Aufruf wird übersprungen.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sync abgeschlossen: %d neu, %d geändert, %d erneut versucht, %d unverändert, %d entfernt.',
            $result['new'],
            $result['changed'],
            $result['retried'],
            $result['unchanged'],
            $result['removed'],
        ));

        if ($result['failed_sources'] !== []) {
            $this->error(sprintf(
                '%d Source(n) fehlgeschlagen: %s',
                count($result['failed_sources']),
                implode(', ', $result['failed_sources']),
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
