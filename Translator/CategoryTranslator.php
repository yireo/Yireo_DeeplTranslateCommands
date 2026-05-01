<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Service\TranslationService;

class CategoryTranslator
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TranslationService $translationService,
        private readonly Config $config,
    ) {
    }

    public function translate(
        int $categoryId,
        int $targetStoreId,
        string $sourceLanguage,
        string $targetLanguage,
        bool $dryRun,
        OutputInterface $output
    ): void {
        $attributes = $this->config->getCategoryAttributes();
        if (empty($attributes)) {
            throw new LocalizedException(__('No category attributes configured for translation.'));
        }

        $defaultCategory = $this->categoryRepository->get($categoryId, Store::DEFAULT_STORE_ID);
        $categoryName = $defaultCategory->getName() ?: 'Unknown';

        $output->writeln(sprintf(
            '%sTranslating category "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $categoryName,
            $categoryId,
            $sourceLanguage,
            $targetLanguage
        ));

        if ($dryRun) {
            $totalChars = 0;
            foreach ($attributes as $attributeCode) {
                $value = (string)$defaultCategory->getData($attributeCode);
                if (empty(trim($value))) {
                    continue;
                }
                $charCount = mb_strlen($value);
                $totalChars += $charCount;
                $output->writeln(sprintf('  - %s: %d chars', $attributeCode, $charCount));
            }
            $output->writeln(sprintf('  Total characters: %d', $totalChars));
            return;
        }

        $storeCategory = $this->categoryRepository->get($categoryId, $targetStoreId);

        foreach ($attributes as $attributeCode) {
            $sourceValue = (string)$defaultCategory->getData($attributeCode);
            if (empty(trim($sourceValue))) {
                continue;
            }

            $translatedValue = $this->translationService->translate($sourceValue, $sourceLanguage, $targetLanguage);
            $storeCategory->setData($attributeCode, $translatedValue);
            $output->writeln(sprintf('  - %s: translated', $attributeCode));
        }

        $storeCategory->setStoreId($targetStoreId);
        $this->categoryRepository->save($storeCategory);
        $output->writeln(sprintf('  Category %d saved.', $categoryId));
    }
}
