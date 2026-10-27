<?php

namespace Tropatt\Shopify;

/**
 * Mandatory GDPR webhooks.
 *
 *   customers/data_request — reply with what the connector stores (nothing but
 *                            the order payload already delivered to the CRM)
 *   customers/redact       — drop locally spooled packets for the customer
 *   shop/redact            — wipe the stored token and the local spool
 */
class GdprHandler
{
    /**
     * @return array{0:int,1:array}
     */
    public static function handle($topic, array $payload, $queueDir = null, $tokenFile = null)
    {
        switch ((string)$topic) {
            case 'customers/data_request':
                return array(200, array(
                    'status' => 'ok',
                    'note' => 'The connector keeps no customer database: order data is forwarded to the CRM and the local spool holds payloads only until delivery.',
                ));

            case 'customers/redact':
                $removed = self::purgeQueue($payload, $queueDir);

                return array(200, array('status' => 'ok', 'spooled_removed' => $removed));

            case 'shop/redact':
                $removed = self::purgeQueue(array(), $queueDir);
                TokenStore::remove($tokenFile);

                return array(200, array('status' => 'ok', 'spooled_removed' => $removed, 'token_removed' => true));

            default:
                return array(404, array('error' => 'Unknown GDPR topic: ' . (string)$topic));
        }
    }

    /**
     * Remove spooled packets (all of them, or only those of one customer).
     *
     * @return int
     */
    public static function purgeQueue(array $payload, $queueDir = null)
    {
        $dir = $queueDir === null ? Config::queueDir() : $queueDir;
        $email = strtolower(trim((string)($payload['customer']['email'] ?? '')));
        $removed = 0;

        foreach (FileQueue::listFiles($dir, 1000) as $path) {
            $canonical = FileQueue::read($path);
            if ($canonical === null) {
                FileQueue::remove($path);
                $removed++;

                continue;
            }

            if ($email === '') {
                FileQueue::remove($path);
                $removed++;

                continue;
            }

            $storedEmail = strtolower((string)($canonical['payload']['customer']['email'] ?? ''));
            if ($storedEmail === $email) {
                FileQueue::remove($path);
                $removed++;
            }
        }

        return $removed;
    }
}
