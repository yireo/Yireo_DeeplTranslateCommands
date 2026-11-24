<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Source\CmsBlockFields;

class CmsBlockTranslator
{
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly CmsBlockFields $cmsBlockFields,
        private readonly FieldSelector $fieldSelector,
        private readonly TranslationService $translationService,
        private readonly Config $config,
        private readonly OutputSanitizer $outputSanitizer,
    ) {
    }

    public function translate(
        int $blockId,
        int $targetStoreId,
        string $sourceLanguage,
        string $targetLanguage,
        bool $dryRun,
        OutputInterface $output,
        bool $force = false
    ): void {
        if ($targetStoreId === Store::DEFAULT_STORE_ID) {
            throw new LocalizedException(__('The admin store view can not be used as translation target.'));
        }

        $fields = $this->cmsBlockFields->filter($this->config->getCmsBlockFields());
        if (empty($fields)) {
            throw new LocalizedException(__('No CMS block fields configured for translation.'));
        }

        $sourceBlock = $this->blockRepository->getById((string)$blockId);
        if (!$sourceBlock instanceof Block) {
            throw new LocalizedException(__('Unable to load CMS block %1.', $blockId));
        }

        $identifier = (string)$sourceBlock->getIdentifier();

        $output->writeln(sprintf(
            '%sTranslating CMS block "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $this->outputSanitizer->sanitize((string)($sourceBlock->getTitle() ?: 'Unknown')),
            $blockId,
            $this->outputSanitizer->sanitize($sourceLanguage),
            $this->outputSanitizer->sanitize($targetLanguage)
        ));

        $existingBlock = $this->findExistingBlock($identifier, $targetStoreId);
        if ($existingBlock !== null) {
            $this->assertSafeTarget($existingBlock, $blockId);
        }

        $fields = $this->fieldSelector->select($fields, $sourceBlock, $existingBlock, $force, $output);

        if ($dryRun) {
            $this->fieldSelector->reportDryRun($fields, $sourceBlock, $output);
            return;
        }

        if (empty($fields)) {
            $output->writeln('  All fields already translated. Skipping CMS block.');
            return;
        }

        $translations = [];
        foreach ($fields as $field => $isHtml) {
            $translations[$field] = $this->translationService->translate(
                (string)$sourceBlock->getData($field),
                $sourceLanguage,
                $targetLanguage,
                $isHtml
            );
            $output->writeln(sprintf('  - %s: translated', $this->outputSanitizer->sanitize($field)));
        }

        $targetBlock = $existingBlock;
        if ($targetBlock === null) {
            $targetBlock = $this->blockFactory->create();
            if (!$targetBlock instanceof Block) {
                throw new LocalizedException(__('Unable to create CMS block.'));
            }

            $targetBlock->setIdentifier($identifier);
            $targetBlock->setStoreId([$targetStoreId]);
            $targetBlock->setIsActive($sourceBlock->isActive());
        }

        foreach ($translations as $field => $translation) {
            $targetBlock->setData($field, $translation);
        }

        $this->blockRepository->save($targetBlock);
        $output->writeln(sprintf(
            '  CMS block "%s" saved for store %d.',
            $this->outputSanitizer->sanitize($identifier),
            $targetStoreId
        ));
    }

    private function assertSafeTarget(Block $existingBlock, int $sourceBlockId): void
    {
        if ((int)$existingBlock->getId() === $sourceBlockId) {
            throw new LocalizedException(__(
                'CMS block %1 is itself assigned to the target store view. Not overwriting the source content.',
                $sourceBlockId
            ));
        }

        $storeIds = array_map('intval', (array)$existingBlock->getData('store_id'));
        if (count($storeIds) !== 1) {
            throw new LocalizedException(__(
                'CMS block %1 is assigned to multiple store views. Not overwriting it.',
                $existingBlock->getId()
            ));
        }
    }

    private function findExistingBlock(string $identifier, int $storeId): ?Block
    {
        $collection = $this->blockCollectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addStoreFilter($storeId, false);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();
        if (!$item->getId()) {
            return null;
        }

        $block = $this->blockRepository->getById((string)$item->getId());

        return $block instanceof Block ? $block : null;
    }
}
