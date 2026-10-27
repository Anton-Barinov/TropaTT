<?php

namespace Tropatt\Crm\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Module settings.
 */
class Config
{
    public const XML_PATH_PREFIX = 'tropatt/general/';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var EncryptorInterface|null */
    private $encryptor;

    public function __construct(ScopeConfigInterface $scopeConfig, ?EncryptorInterface $encryptor = null)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @return mixed
     */
    public function getValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PREFIX . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return (bool)$this->getValue('enabled', $storeId);
    }

    /**
     * @return string
     */
    public function gatewayUrl($storeId = null)
    {
        return rtrim(trim((string)$this->getValue('gateway_url', $storeId)), '/');
    }

    /**
     * @return string
     */
    public function storeKey($storeId = null)
    {
        return trim((string)$this->getValue('store_key', $storeId));
    }

    /**
     * @return string
     */
    public function storeSecret($storeId = null)
    {
        return $this->decrypt((string)$this->getValue('store_secret', $storeId));
    }

    /**
     * @return string
     */
    public function webhookSecret($storeId = null)
    {
        return $this->decrypt((string)$this->getValue('webhook_secret', $storeId));
    }

    /**
     * @return string
     */
    public function defaultStage($storeId = null)
    {
        $stage = trim((string)$this->getValue('default_stage', $storeId));

        return $stage === '' ? 'new' : $stage;
    }

    /**
     * @return string
     */
    public function sourceCode($storeId = null)
    {
        $code = trim((string)$this->getValue('source_code', $storeId));

        return $code === '' ? 'default' : $code;
    }

    /**
     * @return array
     */
    public function statusMapping($storeId = null)
    {
        return StatusMapper::decode((string)$this->getValue('status_mapping', $storeId));
    }

    /**
     * @return bool
     */
    public function isConfigured($storeId = null)
    {
        return $this->gatewayUrl($storeId) !== '' && $this->storeKey($storeId) !== '' && $this->storeSecret($storeId) !== '';
    }

    /**
     * @return string
     */
    private function decrypt($value)
    {
        if ($value === '' || $this->encryptor === null) {
            return trim($value);
        }

        try {
            return trim((string)$this->encryptor->decrypt($value));
        } catch (\Throwable $exception) {
            return trim($value);
        }
    }
}
