<?php

declare(strict_types=1);

namespace App\Controllers;

use Config\Services;

/**
 * Public recipient subscription page.
 */
class Recipient extends WebController
{
    public function show(string $token): string
    {
        $subscriptions = Services::subscriptions();
        $subscription = $subscriptions->findByToken($token);
        $recipient = config('Recipient');

        return $this->render('recipient.show', [
            'title'        => 'Outline Key',
            'subscription' => $subscription,
            'state'        => $subscriptions->resolveRecipientState($subscription),
            'recipient'    => $recipient,
        ]);
    }
}
