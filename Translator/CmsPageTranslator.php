<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Service\TranslationService;

class CmsPageTranslator
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly TranslationService $translationService,
        private readonly Config $config,
    ) {
    }

    public function translate(
        int $pageId,
        int $targetStoreId,
        string $sourceLanguage,
        string $targetLanguage,
        bool $dryRun,
        OutputInterface $output,
        bool $force = false
    ): void {
        $fields = $this->config->getCmsPageFields();
        if (empty($fields)) {
            throw new LocalizedException(__('No CMS page fields configured for translation.'));
        }

        $sourcePage = $this->pageRepository->getById($pageId);
        $pageTitle = $sourcePage->getTitle() ?: 'Unknown';
        $identifier = $sourcePage->getIdentifier();

        $output->writeln(sprintf(
            '%sTranslating CMS page "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $pageTitle,
            $pageId,
            $sourceLanguage,
            $targetLanguage
        ));

        if ($dryRun) {
            $totalChars = 0;
            foreach ($fields as $field) {
                $value = (string)$sourcePage->getData($field);
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

        $existingPage = $this->findExistingPage($identifier, $targetStoreId);

        if ($existingPage) {
            $targetPage = $this->pageRepository->getById($existingPage->getId());
        } else {
            $targetPage = $this->pageFactory->create();
            $targetPage->setIdentifier($identifier);
            $targetPage->setStoreId([$targetStoreId]);
            $targetPage->setIsActive($sourcePage->isActive());
            $targetPage->setPageLayout($sourcePage->getPageLayout());
        }

        if (!$force && !$dryRun && $existingPage) {
            $fieldsToTranslate = [];
            $skippedFields = [];
            
            foreach ($fields as $field) {
                $targetValue = $targetPage->getData($field);
                
                if (empty(trim((string)$targetValue))) {
                    $fieldsToTranslate[] = $field;
                } else {
                    $skippedFields[] = $field;
                }
            }
            
            foreach ($skippedFields as $field) {
                $output->writeln(sprintf('  - %s: skipped (already translated)', $field));
            }
            
            if (empty($fieldsToTranslate)) {
                $output->writeln('  All fields already translated. Skipping CMS page.');
                return;
            }
            
            $fields = $fieldsToTranslate;
        }

        foreach ($fields as $field) {
            $sourceValue = (string)$sourcePage->getData($field);
            if (empty(trim($sourceValue))) {
                continue;
            }

            $translatedValue = $this->translationService->translate($sourceValue, $sourceLanguage, $targetLanguage);
            $targetPage->setData($field, $translatedValue);
            $output->writeln(sprintf('  - %s: translated', $field));
        }

        $targetPage->setStoreId([$targetStoreId]);
        $this->pageRepository->save($targetPage);
        $output->writeln(sprintf('  CMS page "%s" saved for store %d.', $identifier, $targetStoreId));
    }

    private function findExistingPage(string $identifier, int $storeId): ?\Magento\Cms\Api\Data\PageInterface
    {
        $collection = $this->pageCollectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addStoreFilter($storeId, false);
        $collection->setPageSize(1);

        $page = $collection->getFirstItem();
        if ($page && $page->getId()) {
            return $page;
        }

        return null;
    }
}
