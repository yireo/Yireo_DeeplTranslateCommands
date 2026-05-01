<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CmsBlockFields implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'title', 'label' => 'Title'],
            ['value' => 'content', 'label' => 'Content'],
        ];
    }
}
