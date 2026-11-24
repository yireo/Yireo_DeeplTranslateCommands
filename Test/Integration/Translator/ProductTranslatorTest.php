<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Integration\Translator;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Translator\ProductTranslator;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class ProductTranslatorTest extends TestCase
{
    /**
     * @magentoDataFixture Magento/Store/_files/core_fixturestore.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default/yireo_deepl_translate/product/attributes name,description,sku,url_key
     */
    public function testOnlyTranslatedAttributesAreStoredForTargetStore(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $storeId = (int)$objectManager->get(StoreRepositoryInterface::class)->get('fixturestore')->getId();
        $productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $product = $productRepository->get('simple');

        $translationService = $this->createMock(TranslationService::class);
        $translationService->expects($this->exactly(2))->method('translate')
            ->willReturnCallback(fn(string $text) => 'DE ' . $text);

        $translator = $objectManager->create(ProductTranslator::class, ['translationService' => $translationService]);
        $translator->translate((int)$product->getId(), $storeId, 'EN', 'DE', false, new BufferedOutput());

        $this->assertEqualsCanonicalizing(
            ['name', 'description'],
            $this->getStoreAttributeCodes((int)$product->getData($this->getLinkField()), $storeId)
        );

        $storeProduct = $productRepository->get('simple', false, $storeId, true);
        $this->assertSame('DE Simple Product', $storeProduct->getName());
        $this->assertSame('simple', $storeProduct->getSku());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default/yireo_deepl_translate/product/attributes name
     */
    public function testRejectsAdminStore(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $product = $objectManager->get(ProductRepositoryInterface::class)->get('simple');

        $translationService = $this->createMock(TranslationService::class);
        $translationService->expects($this->never())->method('translate');

        $translator = $objectManager->create(ProductTranslator::class, ['translationService' => $translationService]);

        $this->expectExceptionMessage('admin store view');
        $translator->translate((int)$product->getId(), 0, 'EN', 'DE', false, new BufferedOutput());
    }

    private function getStoreAttributeCodes(int $linkId, int $storeId): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $eavConfig = $objectManager->get(EavConfig::class);

        $codes = [];
        foreach (['varchar', 'text', 'int', 'decimal', 'datetime'] as $type) {
            $attributeIds = $connection->fetchCol(
                $connection->select()
                    ->from($resource->getTableName('catalog_product_entity_' . $type), ['attribute_id'])
                    ->where($this->getLinkField() . ' = ?', $linkId)
                    ->where('store_id = ?', $storeId)
            );
            foreach ($attributeIds as $attributeId) {
                $codes[] = $eavConfig->getAttribute('catalog_product', (int)$attributeId)->getAttributeCode();
            }
        }

        return $codes;
    }

    private function getLinkField(): string
    {
        return Bootstrap::getObjectManager()->get(MetadataPool::class)
            ->getMetadata(ProductInterface::class)
            ->getLinkField();
    }
}
