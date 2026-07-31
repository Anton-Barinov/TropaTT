<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

interface AiProviderClientInterface
{
    /**
     * @param array<string,mixed> $provider
     * @return array{ok:bool,code?:string,message?:string,latency_ms?:int,http_status?:int}
     */
    public function testConnection(array $provider, string $secret): array;

    /**
     * @param array<string,mixed> $provider
     * @return array{ok:bool,code?:string,message?:string,items?:list<array{id:string,title:string}>,http_status?:int}
     */
    public function listModels(array $provider, string $secret): array;

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $payload
     * @return array{
     *   ok:bool,
     *   code?:string,
     *   message?:string,
     *   text?:string,
     *   request_tokens?:int,
     *   response_tokens?:int,
     *   total_tokens?:int,
     *   latency_ms?:int,
     *   http_status?:int
     * }
     */
    public function completeText(array $provider, string $secret, array $payload): array;
}
