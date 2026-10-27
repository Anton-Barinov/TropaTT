<?php

namespace Tropatt\Crm\Model;

/**
 * Inbound CRM packet: verification and validation, with no Magento dependencies.
 *
 * The CRM signs the *raw* body (`base64(HMAC-SHA256(secret, timestamp . '.' . body))`)
 * and ships the signature in HTTP headers. A service contract can receive neither:
 * webapi maps the JSON body onto method arguments only, which is why the former
 * `POST /V1/tropatt/webhook` route answered 400 (`InputException`) before its method
 * was ever reached, and why a signature over a re-encoded body could never match.
 * The receiving controller reads the headers and the raw body itself and hands both
 * to this class, which stays testable without the platform.
 */
class InboundWebhook
{
    public const SIGNATURE_HEADER = 'X-TropaTT-Signature';
    public const TIMESTAMP_HEADER = 'X-TropaTT-Timestamp';

    /** @var Signature */
    private $signature;

    public function __construct(Signature $signature)
    {
        $this->signature = $signature;
    }

    /**
     * Verify the signature over the raw body, then validate the payload.
     *
     * @param string $rawBody Body exactly as it arrived on the wire.
     * @param string $signatureHeader Value of `X-TropaTT-Signature`.
     * @param string $timestampHeader Value of `X-TropaTT-Timestamp`.
     * @param string $secret Store webhook secret.
     * @return array{ok:bool, http_code:int, error:string, data:array, external_order_id:int}
     */
    public function parse($rawBody, $signatureHeader, $timestampHeader, $secret)
    {
        $signatureHeader = (string)$signatureHeader;
        $timestampHeader = (string)$timestampHeader;
        $secret = (string)$secret;

        if ($secret === '' || $signatureHeader === '' || $timestampHeader === '') {
            return $this->failure(401, 'Missing authentication headers');
        }

        if (!$this->signature->withinTolerance($timestampHeader)) {
            return $this->failure(401, 'Timestamp out of tolerance window');
        }

        if (!$this->signature->verifyWebhook($timestampHeader, (string)$rawBody, $signatureHeader, $secret)) {
            return $this->failure(401, 'Invalid cryptographic signature');
        }

        $data = json_decode((string)$rawBody, true);
        if (!is_array($data)) {
            return $this->failure(400, 'Invalid JSON payload');
        }

        $orderId = (int)(isset($data['external_order_id']) ? $data['external_order_id'] : 0);
        if ($orderId <= 0) {
            return $this->failure(422, 'Missing external_order_id');
        }

        return array(
            'ok' => true,
            'http_code' => 200,
            'error' => '',
            'data' => $data,
            'external_order_id' => $orderId,
        );
    }

    /**
     * @param int $httpCode
     * @param string $message
     * @return array
     */
    private function failure($httpCode, $message)
    {
        return array(
            'ok' => false,
            'http_code' => (int)$httpCode,
            'error' => (string)$message,
            'data' => array(),
            'external_order_id' => 0,
        );
    }
}
