<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Integration\Source;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Source\CategoryAttributes;
use Yireo\DeeplTranslateCommands\Source\ProductAttributes;

class ProductAttributesTest extends TestCase
{
    public function testProductOptionsOnlyContainStoreScopedAttributes(): void
    {
        $codes = array_column(Bootstrap::getObjectManager()->get(ProductAttributes::class)->toOptionArray(), 'value');

        $this->assertContains('name', $codes);
        $this->assertContains('description', $codes);
        $this->assertNotContains('sku', $codes);
        $this->assertNotContains('url_key', $codes);
        $this->assertNotContains('custom_layout_update', $codes);
    }

    public function testCategoryOptionsOnlyContainStoreScopedAttributes(): void
    {
        $codes = array_column(Bootstrap::getObjectManager()->get(CategoryAttributes::class)->toOptionArray(), 'value');

        $this->assertContains('name', $codes);
        $this->assertNotContains('url_key', $codes);
        $this->assertNotContains('url_path', $codes);
    }
}
