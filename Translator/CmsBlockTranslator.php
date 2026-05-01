<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Service\TranslationService;

class CmsBlockTranslator
{
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly TranslationService $translationService,
        private readonly Config $config,
    ) {
    }

    public function translate(
        int $blockId,
        int $targetStoreId,
        string $sourceLanguage,
        string $targetLanguage,
        bool $dryRun,
        OutputInterface $output
    ): void {
        $fields = $this->config->getCmsBlockFields();
        if (empty($fields)) {
            throw new LocalizedException(__('No CMS block fields configured for translation.'));
        }

        $sourceBlock = $this->blockRepository->getById($blockId);
        $blockTitle = $sourceBlock->getTitle() ?: 'Unknown';
        $identifier = $sourceBlock->getIdentifier();

        $output->writeln(sprintf(
            '%sTranslating CMS block "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $blockTitle,
            $blockId,
            $sourceLanguage,
            $targetLanguage
        ));

        if ($dryRun) {
            $totalChars = 0;
            foreach ($fields as $field) {
                $value = (string)$sourceBlock->getData($field);
                if (empty(trim($value))) {
                    continue;
                }
                $charCount = mb_strlen($value);
                $totalChars += $charCount;
                $output->writeln(sprintf('  - %s: %d chars', $field, $charCount));
            }
            $output->writeln(sprintf('  Total characters: %d', $totalChars));
            return;
        }

        $existingBlock = $this->findExistingBlock($identifier, $targetStoreId);

        if ($existingBlock) {
            $targetBlock = $this->blockRepository->getById($existingBlock->getId());
        } else {
            $targetBlock = $this->blockFactory->create();
            $targetBlock->setIdentifier($identifier);
            $targetBlock->setStoreId([$targetStoreId]);
            $targetBlock->setIsActive($sourceBlock->isActive());
        }

        foreach ($fields as $field) {
            $sourceValue = (string)$sourceBlock->getData($field);
            if (empty(trim($sourceValue))) {
                continue;
            }

            $translatedValue = $this->translationService->translate($sourceValue, $sourceLanguage, $targetLanguage);
            $targetBlock->setData($field, $translatedValue);
            $output->writeln(sprintf('  - %s: translated', $field));
        }

        $targetBlock->setStoreId([$targetStoreId]);
        $this->blockRepository->save($targetBlock);
        $output->writeln(sprintf('  CMS block "%s" saved for store %d.', $identifier, $targetStoreId));
    }

    private function findExistingBlock(string $identifier, int $storeId): ?\Magento\Cms\Api\Data\BlockInterface
    {
        $collection = $this->blockCollectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addStoreFilter($storeId, false);
        $collection->setPageSize(1);

        $block = $collection->getFirstItem();
        if ($block && $block->getId()) {
            return $block;
        }

        return null;
    }
}
