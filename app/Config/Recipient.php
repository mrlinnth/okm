<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Contact details displayed on recipient public pages.
 */
class Recipient extends BaseConfig
{
    public string $telegramHandle = 't.me/okm_admin';

    public string $viberNumber = '+959000000000';
}
