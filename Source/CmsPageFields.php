<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CmsPageFields implements OptionSourceInterface
{
    private const HTML_FIELDS = ['content'];

    public function toOptionArray(): array
    {
        return [
            ['value' => 'title', 'label' => 'Title'],
            ['value' => 'content', 'label' => 'Content'],
            ['value' => 'content_heading', 'label' => 'Content Heading'],
            ['value' => 'meta_title', 'label' => 'Meta Title'],
            ['value' => 'meta_description', 'label' => 'Meta Description'],
            ['value' => 'meta_keywords', 'label' => 'Meta Keywords'],
        ];
    }

    /**
     * @param string[] $fields
     * @return array<string, bool> Field => whether the value is HTML
     */
    public function filter(array $fields): array
    {
        $allowed = array_column($this->toOptionArray(), 'value');
        $filtered = [];
        foreach ($fields as $field) {
            if (in_array($field, $allowed, true)) {
                $filtered[$field] = in_array($field, self::HTML_FIELDS, true);
            }
        }

        return $filtered;
    }
}
