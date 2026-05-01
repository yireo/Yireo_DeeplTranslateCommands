<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    private const XML_PATH_ENABLED = 'yireo_deepl_translate/general/enabled';
    private const XML_PATH_API_KEY = 'yireo_deepl_translate/general/api_key';
    private const XML_PATH_PRODUCT_ATTRIBUTES = 'yireo_deepl_translate/product/attributes';
    private const XML_PATH_CATEGORY_ATTRIBUTES = 'yireo_deepl_translate/category/attributes';
    private const XML_PATH_CMS_PAGE_FIELDS = 'yireo_deepl_translate/cms_page/fields';
    private const XML_PATH_CMS_BLOCK_FIELDS = 'yireo_deepl_translate/cms_block/fields';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    public function getApiKey(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_API_KEY);
        if (empty($value)) {
            return '';
        }

        return $this->encryptor->decrypt($value);
    }

    public function getProductAttributes(): array
    {
        return $this->getMultiselectValues(self::XML_PATH_PRODUCT_ATTRIBUTES);
    }

    public function getCategoryAttributes(): array
    {
        return $this->getMultiselectValues(self::XML_PATH_CATEGORY_ATTRIBUTES);
    }

    public function getCmsPageFields(): array
    {
        return $this->getMultiselectValues(self::XML_PATH_CMS_PAGE_FIELDS);
    }

    public function getCmsBlockFields(): array
    {
        return $this->getMultiselectValues(self::XML_PATH_CMS_BLOCK_FIELDS);
    }

    private function getMultiselectValues(string $path): array
    {
        $value = (string)$this->scopeConfig->getValue($path);
        if (empty($value)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $value)));
    }
}
