<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Reconciles every active saved server against the ledger: imports Outline
 * keys created outside the app, and removes ledger records whose key is
 * gone. Fully automated — no admin interaction.
 *
 * Separate concern from `subscriptions:expire`, run on its own schedule.
 * Suggested crontab (daily, offset from the 00:05 expiry slot so both jobs
 * don't hit Cockpit/Outline at once) — a deploy-time note, not installed by
 * the app itself:
 *
 *   10 0 * * * cd /path/to/app && php spark servers:sync
 */
class SyncServers extends BaseCommand
{
    protected $group       = 'Subscriptions';
    protected $name        = 'servers:sync';
    protected $description  = 'Reconcile every active saved server: import orphan Outline keys, remove stale ledger records.';
    protected $usage        = 'servers:sync';

    public function run(array $params): int
    {
        $savedServers = Services::savedServers();

        $imported = 0;
        $removed  = 0;
        $failed   = 0;

        foreach ($savedServers->list() as $server) {
            if (($server['active'] ?? false) !== true) {
                continue;
            }

            $serverId = (string) ($server['_id'] ?? '');

            try {
                $summary = $savedServers->reconcileServer($serverId);
            } catch (\Throwable $e) {
                $failed++;
                log_message('error', 'servers:sync could not diff {id}: {error}', ['id' => $serverId, 'error' => $e->getMessage()]);

                continue;
            }

            $imported += count($summary['imported']);
            $removed  += count($summary['removed']);
            $failed   += $summary['failed'];
        }

        CLI::write("Imported: {$imported}, Removed: {$removed}, Failed: {$failed}");

        return EXIT_SUCCESS;
    }
}
