<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\SubscriptionsService;
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
        $savedServers  = Services::savedServers();
        $subscriptions = Services::subscriptions();
        $expiryDate    = SubscriptionsService::addMonthsClamped(new \DateTimeImmutable('today'), 1);

        $imported = 0;
        $removed  = 0;
        $failed   = 0;

        foreach ($savedServers->list() as $server) {
            if (($server['active'] ?? false) !== true) {
                continue;
            }

            $serverId = (string) ($server['_id'] ?? '');

            try {
                $diff = $savedServers->diffServer($serverId);
            } catch (\Throwable $e) {
                $failed++;
                log_message('error', 'servers:sync could not diff {id}: {error}', ['id' => $serverId, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($diff['foundOnServer'] as $key) {
                try {
                    $subscriptions->createFromOutlineKey($serverId, $key, $expiryDate);
                    $imported++;
                } catch (\Throwable $e) {
                    $failed++;
                    log_message('error', 'servers:sync could not import {name} on {id}: {error}', [
                        'name'  => (string) ($key['name'] ?? ''),
                        'id'    => $serverId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($diff['missingOnServer'] as $subscription) {
                $subscriptionId = (string) ($subscription['_id'] ?? '');

                try {
                    if ($subscriptions->removeRecord($subscriptionId)) {
                        $removed++;
                    } else {
                        $failed++;
                        log_message('error', 'servers:sync could not remove stale record {id}', ['id' => $subscriptionId]);
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    log_message('error', 'servers:sync could not remove stale record {id}: {error}', [
                        'id'    => $subscriptionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        CLI::write("Imported: {$imported}, Removed: {$removed}, Failed: {$failed}");

        return EXIT_SUCCESS;
    }
}
