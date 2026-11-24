<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Unit\Service;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Service\AttributeFilter;

class AttributeFilterTest extends TestCase
{
    public function testAllowsStoreScopedTextAttribute(): void
    {
        $this->assertTrue($this->createFilter()->isAllowed($this->createAttribute('name')));
    }

    /**
     * @dataProvider getBlockedCodes
     */
    public function testBlocksSensitiveAttributeCodes(string $code): void
    {
        $this->assertFalse($this->createFilter()->isAllowed($this->createAttribute($code)));
    }

    public static function getBlockedCodes(): array
    {
        return [['sku'], ['url_key'], ['url_path'], ['page_layout'], ['custom_layout_update'], ['custom_layout_update_file']];
    }

    public function testBlocksGlobalAndWebsiteAttributes(): void
    {
        $filter = $this->createFilter();
        $this->assertFalse($filter->isAllowed($this->createAttribute('foo', scope: ScopedAttributeInterface::SCOPE_GLOBAL)));
        $this->assertFalse($filter->isAllowed($this->createAttribute('foo', scope: ScopedAttributeInterface::SCOPE_WEBSITE)));
    }

    public function testBlocksStaticSelectAndInvisibleAttributes(): void
    {
        $filter = $this->createFilter();
        $this->assertFalse($filter->isAllowed($this->createAttribute('foo', backendType: 'static')));
        $this->assertFalse($filter->isAllowed($this->createAttribute('foo', input: 'select')));
        $this->assertFalse($filter->isAllowed($this->createAttribute('foo', visible: false)));
    }

    public function testIsHtml(): void
    {
        $filter = $this->createFilter();
        $this->assertTrue($filter->isHtml($this->createAttribute('description', input: 'textarea', wysiwyg: true)));
        $this->assertFalse($filter->isHtml($this->createAttribute('meta_description', input: 'textarea')));
        $this->assertFalse($filter->isHtml($this->createAttribute('name', wysiwyg: true)));
    }

    private function createFilter(): AttributeFilter
    {
        return new AttributeFilter($this->createMock(EavConfig::class));
    }

    private function createAttribute(
        string $code,
        string $input = 'text',
        int $scope = ScopedAttributeInterface::SCOPE_STORE,
        string $backendType = 'varchar',
        bool $visible = true,
        bool $wysiwyg = false
    ): Attribute {
        $attribute = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAttributeCode',
                'getFrontendInput',
                'getIsGlobal',
                'getBackendType',
                'getIsVisible',
                'getIsWysiwygEnabled',
                'getIsHtmlAllowedOnFront',
            ])
            ->getMock();
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getFrontendInput')->willReturn($input);
        $attribute->method('getIsGlobal')->willReturn($scope);
        $attribute->method('getBackendType')->willReturn($backendType);
        $attribute->method('getIsVisible')->willReturn($visible);
        $attribute->method('getIsWysiwygEnabled')->willReturn($wysiwyg);
        $attribute->method('getIsHtmlAllowedOnFront')->willReturn(false);

        return $attribute;
    }
}
