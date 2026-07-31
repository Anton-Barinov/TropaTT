<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AiProviderClientFactory
{
    public function __construct(
        private readonly OpenAiCompatibleProviderClient $openAiCompatibleClient,
        private readonly MockAiProviderClient $mockClient,
        private readonly CustomHttpProviderClient $customHttpClient
    ) {
    }

    /**
     * @param array<string,mixed> $provider
     */
    public function forProvider(array $provider): AiProviderClientInterface
    {
        $code = strtolower(trim((string)($provider['provider_code'] ?? '')));
        if ($code === 'mock' || $code === 'fake') {
            return $this->mockClient;
        }
        if ($code === 'custom_http' || $code === 'custom') {
            return $this->customHttpClient;
        }
        if ($code === '' || $code === 'openai_compatible' || $code === 'openai') {
            return $this->openAiCompatibleClient;
        }

        return $this->openAiCompatibleClient;
    }
}
