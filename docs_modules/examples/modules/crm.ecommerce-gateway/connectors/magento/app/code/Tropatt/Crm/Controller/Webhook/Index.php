<?php

namespace Tropatt\Crm\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Tropatt\Crm\Model\InboundWebhook;
use Tropatt\Crm\Model\Webhook;

/**
 * Receiver of the CRM -> store status webhook: `POST /tropatt/webhook/index`.
 *
 * A frontend controller (not a service contract) is mandatory here. The CRM sends the
 * signature in `X-TropaTT-Signature` / `X-TropaTT-Timestamp` and signs the *raw* body,
 * while webapi only maps the JSON body onto method arguments and never exposes headers -
 * the former `POST /V1/tropatt/webhook` route therefore failed with an InputException
 * before its method ran. `Request\Http` is the API class that carries `getHeader()` and
 * `getContent()`; `RequestInterface` declares neither.
 *
 * The action implements `HttpPostActionInterface`, so the router answers 404 for any
 * other HTTP method: the endpoint stays anonymous by design (the HMAC over the raw body
 * is the authentication) but is not reachable through GET.
 */
class Index implements HttpPostActionInterface
{
    /** @var HttpRequest */
    private $request;

    /** @var JsonFactory */
    private $jsonFactory;

    /** @var Webhook */
    private $webhook;

    public function __construct(HttpRequest $request, JsonFactory $jsonFactory, Webhook $webhook)
    {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->webhook = $webhook;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        // Magento matches header names case-insensitively; getContent() returns the body
        // exactly as it arrived, which is what the signature was computed over.
        $signature = (string)$this->request->getHeader(InboundWebhook::SIGNATURE_HEADER);
        $timestamp = (string)$this->request->getHeader(InboundWebhook::TIMESTAMP_HEADER);

        $outcome = $this->webhook->handle((string)$this->request->getContent(), $signature, $timestamp);

        return $this->jsonFactory->create()
            ->setHttpResponseCode((int)$outcome['http_code'])
            ->setData($outcome['body']);
    }
}
