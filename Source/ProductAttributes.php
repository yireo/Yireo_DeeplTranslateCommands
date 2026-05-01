<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Source;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Model\Product;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributes implements OptionSourceInterface
{
    public function __construct(
        private readonly EavConfig $eavConfig,
    ) {
    }

    public function toOptionArray(): array
    {
        $entityType = $this->eavConfig->getEntityType(Product::ENTITY);
        $attributes = $this->eavConfig->getEntityAttributes($entityType->getEntityTypeId());
        $options = [];

        foreach ($attributes as $attribute) {
            $frontendInput = $attribute->getFrontendInput();
            if (!in_array($frontendInput, ['text', 'textarea'])) {
                continue;
            }

            if (!$attribute->getIsVisible()) {
                continue;
            }

            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getDefaultFrontendLabel()
                    ? $attribute->getDefaultFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')'
                    : $attribute->getAttributeCode(),
            ];
        }

        usort($options, fn(array $a, array $b) => strcmp((string)$a['label'], (string)$b['label']));

        return $options;
    }
}
