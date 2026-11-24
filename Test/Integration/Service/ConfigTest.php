<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Integration\Service;

use Magento\Config\Model\Config\TypePool;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testApiKeyIsMarkedAsSensitive(): void
    {
        $typePool = Bootstrap::getObjectManager()->get(TypePool::class);

        $this->assertTrue($typePool->isPresent('yireo_deepl_translate/general/api_key', TypePool::TYPE_SENSITIVE));
    }
}
