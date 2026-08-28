<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Deletes the Outline keys of subscriptions that are past their grace
 * period and marks them expired.
 *
 * CodeIgniter 4 has no built-in scheduler, so this is a standalone CLI
 * entry point. Run it from cron daily at 00:05 UTC (matching the old app's
 * schedule) — wiring the crontab entry on the host is a deploy-time step,
 * not something the app installs itself:
 *
 *   5 0 * * * cd /path/to/app && php spark subscriptions:expire
 */
class ExpireSubscriptions extends BaseCommand
{
    protected $group       = 'Subscriptions';
    protected $name        = 'subscriptions:expire';
    protected $description  = 'Delete Outline keys for subscriptions past their grace period and mark them expired.';
    protected $usage        = 'subscriptions:expire';

    public function run(array $params): int
    {
        $subscriptions = Services::subscriptions();

        $expired = 0;
        $failed  = 0;

        foreach ($subscriptions->findExpirable() as $subscription) {
            $result = $subscriptions->processExpiry($subscription);

            if ($result['outcome'] === 'expired') {
                $expired++;

                continue;
            }

            $failed++;
            log_message('error', 'subscriptions:expire could not expire {id}: {error}', [
                'id'    => $result['id'],
                'error' => $result['error'] ?? 'unknown error',
            ]);
        }

        CLI::write("Expired: {$expired}, Failed: {$failed}");

        return EXIT_SUCCESS;
    }
}
