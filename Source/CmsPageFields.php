<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CmsPageFields implements OptionSourceInterface
{
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
}
