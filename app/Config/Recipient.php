<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Contact details shown in the recipient public page footer.
 *
 * Override per environment in .env:
 *   recipient.telegramUsername = 'your_admin'
 *   recipient.viberNumber      = '+959xxxxxxxx'
 */
class Recipient extends BaseConfig
{
    /**
     * Telegram username without the leading @ — the page links to
     * https://t.me/<username>.
     */
    public string $telegramUsername = 'okm_admin';

    /**
     * Viber account phone number in international format — the page links to
     * viber://chat?number=<number>.
     */
    public string $viberNumber = '+959000000000';
}
