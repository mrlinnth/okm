<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Controls the automated subscription-expiry job.
 */
class Expiry extends BaseConfig
{
    /**
     * Days past a subscription's expiryDate before the expiry job deletes
     * its Outline key and marks it expired. A subscription is eligible when
     * today > expiryDate + gracePeriodDays.
     */
    public int $gracePeriodDays = 3;
}
