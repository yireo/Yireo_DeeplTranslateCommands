<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Source;

use Magento\Catalog\Model\Category;
use Magento\Framework\Data\OptionSourceInterface;
use Yireo\DeeplTranslateCommands\Service\AttributeFilter;

class CategoryAttributes implements OptionSourceInterface
{
    public function __construct(
        private readonly AttributeFilter $attributeFilter,
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->attributeFilter->getAllowedAttributes(Category::ENTITY) as $code => $attribute) {
            $label = (string)$attribute->getDefaultFrontendLabel();
            $options[] = [
                'value' => $code,
                'label' => $label !== '' ? $label . ' (' . $code . ')' : $code,
            ];
        }

        usort($options, fn(array $a, array $b) => strcmp((string)$a['label'], (string)$b['label']));

        return $options;
    }
}
