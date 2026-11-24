<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\AttributeFilter;
use Yireo\DeeplTranslateCommands\Service\TranslationService;

class CategoryTranslator
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryResource $categoryResource,
        private readonly EventManager $eventManager,
        private readonly AttributeFilter $attributeFilter,
        private readonly FieldSelector $fieldSelector,
        private readonly TranslationService $translationService,
        private readonly Config $config,
        private readonly OutputSanitizer $outputSanitizer,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    public function translate(
        int $categoryId,
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

        if ($this->storeManager->hasSingleStore()) {
            throw new LocalizedException(__('Translating requires multiple store views.'));
        }

        $attributes = $this->attributeFilter->filter(Category::ENTITY, $this->config->getCategoryAttributes());
        if (empty($attributes)) {
            throw new LocalizedException(__('No translatable category attributes configured for translation.'));
        }

        $defaultCategory = $this->categoryRepository->get($categoryId, Store::DEFAULT_STORE_ID);
        $storeCategory = $this->categoryRepository->get($categoryId, $targetStoreId);
        if (!$defaultCategory instanceof Category || !$storeCategory instanceof Category) {
            throw new LocalizedException(__('Unable to load category %1.', $categoryId));
        }

        $output->writeln(sprintf(
            '%sTranslating category "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $this->outputSanitizer->sanitize((string)($defaultCategory->getName() ?: 'Unknown')),
            $categoryId,
            $this->outputSanitizer->sanitize($sourceLanguage),
            $this->outputSanitizer->sanitize($targetLanguage)
        ));

        $attributes = $this->fieldSelector->select($attributes, $defaultCategory, $storeCategory, $force, $output);

        if ($dryRun) {
            $this->fieldSelector->reportDryRun($attributes, $defaultCategory, $output);
            return;
        }

        if (empty($attributes)) {
            $output->writeln('  All attributes already translated. Skipping category.');
            return;
        }

        $translations = [];
        foreach ($attributes as $attributeCode => $isHtml) {
            $translations[$attributeCode] = $this->translationService->translate(
                (string)$defaultCategory->getData($attributeCode),
                $sourceLanguage,
                $targetLanguage,
                $isHtml
            );
            $output->writeln(sprintf('  - %s: translated', $this->outputSanitizer->sanitize($attributeCode)));
        }

        $storeCategory->setStoreId($targetStoreId);
        foreach ($translations as $attributeCode => $translation) {
            $storeCategory->setData($attributeCode, $translation);
            $this->categoryResource->saveAttribute($storeCategory, $attributeCode);
        }

        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $storeCategory]);
        $output->writeln(sprintf('  Category %d saved.', $categoryId));
    }
}
