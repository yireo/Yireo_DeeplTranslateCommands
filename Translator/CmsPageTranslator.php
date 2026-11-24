<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Source\CmsPageFields;

class CmsPageTranslator
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly CmsPageFields $cmsPageFields,
        private readonly FieldSelector $fieldSelector,
        private readonly TranslationService $translationService,
        private readonly Config $config,
        private readonly OutputSanitizer $outputSanitizer,
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
        if ($targetStoreId === Store::DEFAULT_STORE_ID) {
            throw new LocalizedException(__('The admin store view can not be used as translation target.'));
        }

        $fields = $this->cmsPageFields->filter($this->config->getCmsPageFields());
        if (empty($fields)) {
            throw new LocalizedException(__('No CMS page fields configured for translation.'));
        }

        $sourcePage = $this->pageRepository->getById($pageId);
        if (!$sourcePage instanceof Page) {
            throw new LocalizedException(__('Unable to load CMS page %1.', $pageId));
        }

        $identifier = (string)$sourcePage->getIdentifier();

        $output->writeln(sprintf(
            '%sTranslating CMS page "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $this->outputSanitizer->sanitize((string)($sourcePage->getTitle() ?: 'Unknown')),
            $pageId,
            $this->outputSanitizer->sanitize($sourceLanguage),
            $this->outputSanitizer->sanitize($targetLanguage)
        ));

        $existingPage = $this->findExistingPage($identifier, $targetStoreId);
        if ($existingPage !== null) {
            $this->assertSafeTarget($existingPage, $pageId);
        }

        $fields = $this->fieldSelector->select($fields, $sourcePage, $existingPage, $force, $output);

        if ($dryRun) {
            $this->fieldSelector->reportDryRun($fields, $sourcePage, $output);
            return;
        }

        if (empty($fields)) {
            $output->writeln('  All fields already translated. Skipping CMS page.');
            return;
        }

        $translations = [];
        foreach ($fields as $field => $isHtml) {
            $translations[$field] = $this->translationService->translate(
                (string)$sourcePage->getData($field),
                $sourceLanguage,
                $targetLanguage,
                $isHtml
            );
            $output->writeln(sprintf('  - %s: translated', $this->outputSanitizer->sanitize($field)));
        }

        $targetPage = $existingPage;
        if ($targetPage === null) {
            $targetPage = $this->pageFactory->create();
            if (!$targetPage instanceof Page) {
                throw new LocalizedException(__('Unable to create CMS page.'));
            }

            $targetPage->setIdentifier($identifier);
            $targetPage->setStoreId([$targetStoreId]);
            $targetPage->setIsActive($sourcePage->isActive());
            $targetPage->setPageLayout($sourcePage->getPageLayout());
        }

        foreach ($translations as $field => $translation) {
            $targetPage->setData($field, $translation);
        }

        $this->pageRepository->save($targetPage);
        $output->writeln(sprintf(
            '  CMS page "%s" saved for store %d.',
            $this->outputSanitizer->sanitize($identifier),
            $targetStoreId
        ));
    }

    private function assertSafeTarget(Page $existingPage, int $sourcePageId): void
    {
        if ((int)$existingPage->getId() === $sourcePageId) {
            throw new LocalizedException(__(
                'CMS page %1 is itself assigned to the target store view. Not overwriting the source content.',
                $sourcePageId
            ));
        }

        $storeIds = array_map('intval', (array)$existingPage->getData('store_id'));
        if (count($storeIds) !== 1) {
            throw new LocalizedException(__(
                'CMS page %1 is assigned to multiple store views. Not overwriting it.',
                $existingPage->getId()
            ));
        }
    }

    private function findExistingPage(string $identifier, int $storeId): ?Page
    {
        $collection = $this->pageCollectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addStoreFilter($storeId, false);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();
        if (!$item->getId()) {
            return null;
        }

        $page = $this->pageRepository->getById((int)$item->getId());

        return $page instanceof Page ? $page : null;
    }
}
