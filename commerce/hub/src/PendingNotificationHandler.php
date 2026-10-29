<?php

declare(strict_types=1);

namespace Commerce\Hub;

use Commerce\Core\Integration\Handler;

/**
 * Placeholder until an email provider is chosen and verified (see docs/integrations.md).
 * It fails visibly instead of pretending to send: rows wait in the queue with a clear reason,
 * and are delivered once a real handler is registered and they are retried.
 */
final class PendingNotificationHandler implements Handler
{
    public function handle(array $payload): void
    {
        throw new \RuntimeException('No email or WhatsApp provider is configured yet, so this message was not sent.');
    }
}
