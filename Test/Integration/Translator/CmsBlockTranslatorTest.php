<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Integration\Translator;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Translator\CmsBlockTranslator;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class CmsBlockTranslatorTest extends TestCase
{
    /**
     * @magentoDataFixture Magento/Store/_files/core_fixturestore.php
     * @magentoConfigFixture default/yireo_deepl_translate/cms_block/fields title,content
     */
    public function testDoesNotOverwriteBlockAssignedToMultipleStores(): void
    {
        $targetStoreId = $this->getFixtureStoreId();
        $blockId = $this->createBlock('deepl-multi', [1, $targetStoreId]);

        $translationService = $this->createMock(TranslationService::class);
        $translationService->expects($this->never())->method('translate');

        try {
            $this->createTranslator($translationService)
                ->translate($blockId, $targetStoreId, 'EN', 'DE', false, new BufferedOutput());
            $this->fail('Expected exception');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('Not overwriting', $e->getMessage());
        }

        $block = Bootstrap::getObjectManager()->create(BlockRepositoryInterface::class)->getById($blockId);
        $this->assertSame('<p>Hello</p>', $block->getContent());
        $this->assertEqualsCanonicalizing([1, $targetStoreId], array_map('intval', (array)$block->getData('store_id')));
    }

    /**
     * @magentoDataFixture Magento/Store/_files/core_fixturestore.php
     * @magentoConfigFixture default/yireo_deepl_translate/cms_block/fields title,content,identifier
     */
    public function testCreatesSeparateBlockForTargetStore(): void
    {
        $targetStoreId = $this->getFixtureStoreId();
        $blockId = $this->createBlock('deepl-all', [0]);

        $translationService = $this->createMock(TranslationService::class);
        $translationService->expects($this->exactly(2))->method('translate')
            ->willReturnCallback(fn(string $text, string $source, string $target, bool $isHtml) => $isHtml
                ? '<p>Hallo</p>'
                : 'DE ' . $text);

        $this->createTranslator($translationService)
            ->translate($blockId, $targetStoreId, 'EN', 'DE', false, new BufferedOutput());

        $collection = Bootstrap::getObjectManager()->create(CollectionFactory::class)->create();
        $collection->addFieldToFilter('identifier', 'deepl-all');
        $collection->addStoreFilter($targetStoreId, false);
        $targetBlock = $collection->getFirstItem();

        $this->assertNotEquals($blockId, (int)$targetBlock->getId());
        $this->assertSame('<p>Hallo</p>', $targetBlock->getContent());
        $this->assertSame('DE Block', $targetBlock->getTitle());

        $sourceBlock = Bootstrap::getObjectManager()->create(BlockRepositoryInterface::class)->getById($blockId);
        $this->assertSame('<p>Hello</p>', $sourceBlock->getContent());
        $this->assertSame([0], array_map('intval', (array)$sourceBlock->getData('store_id')));
    }

    private function createTranslator(TranslationService $translationService): CmsBlockTranslator
    {
        return Bootstrap::getObjectManager()->create(
            CmsBlockTranslator::class,
            ['translationService' => $translationService]
        );
    }

    private function createBlock(string $identifier, array $storeIds): int
    {
        $objectManager = Bootstrap::getObjectManager();
        $block = $objectManager->get(BlockInterfaceFactory::class)->create();
        $block->setIdentifier($identifier);
        $block->setTitle('Block');
        $block->setContent('<p>Hello</p>');
        $block->setIsActive(true);
        $block->setData('store_id', $storeIds);

        return (int)$objectManager->get(BlockRepositoryInterface::class)->save($block)->getId();
    }

    private function getFixtureStoreId(): int
    {
        return (int)Bootstrap::getObjectManager()->get(StoreRepositoryInterface::class)->get('fixturestore')->getId();
    }
}
