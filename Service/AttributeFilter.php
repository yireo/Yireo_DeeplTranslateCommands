<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class AttributeFilter
{
    private const BLOCKED_ATTRIBUTES = [
        'sku',
        'url_key',
        'url_path',
        'page_layout',
        'custom_design',
        'custom_layout',
    ];

    private const BLOCKED_PREFIXES = [
        'custom_layout_update',
    ];

    public function __construct(
        private readonly EavConfig $eavConfig,
    ) {
    }

    public function isAllowed(Attribute $attribute): bool
    {
        $code = (string)$attribute->getAttributeCode();
        if (in_array($code, self::BLOCKED_ATTRIBUTES, true)) {
            return false;
        }

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return false;
            }
        }

        if (!in_array($attribute->getFrontendInput(), ['text', 'textarea'], true)) {
            return false;
        }

        if ($attribute->getBackendType() === 'static') {
            return false;
        }

        if (!$attribute->getIsVisible()) {
            return false;
        }

        return (int)$attribute->getIsGlobal() === ScopedAttributeInterface::SCOPE_STORE;
    }

    public function isHtml(Attribute $attribute): bool
    {
        return $attribute->getFrontendInput() === 'textarea'
            && ($attribute->getIsWysiwygEnabled() || $attribute->getIsHtmlAllowedOnFront());
    }

    /**
     * @return Attribute[]
     */
    public function getAllowedAttributes(string $entityType): array
    {
        $entityTypeId = $this->eavConfig->getEntityType($entityType)->getEntityTypeId();
        $allowed = [];
        foreach ($this->eavConfig->getEntityAttributes($entityTypeId) as $attribute) {
            if ($attribute instanceof Attribute && $this->isAllowed($attribute)) {
                $allowed[(string)$attribute->getAttributeCode()] = $attribute;
            }
        }

        return $allowed;
    }

    /**
     * @param string[] $attributeCodes
     * @return array<string, bool> Attribute code => whether the value is HTML
     */
    public function filter(string $entityType, array $attributeCodes): array
    {
        $allowed = $this->getAllowedAttributes($entityType);
        $fields = [];
        foreach ($attributeCodes as $attributeCode) {
            if (isset($allowed[$attributeCode])) {
                $fields[$attributeCode] = $this->isHtml($allowed[$attributeCode]);
            }
        }

        return $fields;
    }
}
