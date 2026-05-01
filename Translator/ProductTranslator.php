<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Service\TranslationService;

class ProductTranslator
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TranslationService $translationService,
        private readonly Config $config,
    ) {
    }

    public function translate(
        int $productId,
        int $targetStoreId,
        string $sourceLanguage,
        string $targetLanguage,
        bool $dryRun,
        OutputInterface $output,
        bool $force = false
    ): void {
        $attributes = $this->config->getProductAttributes();
        if (empty($attributes)) {
            throw new LocalizedException(__('No product attributes configured for translation.'));
        }

        $defaultProduct = $this->productRepository->getById($productId, false, Store::DEFAULT_STORE_ID);
        $productName = $defaultProduct->getName() ?: 'Unknown';

        $output->writeln(sprintf(
            '%sTranslating product "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $productName,
            $productId,
            $sourceLanguage,
            $targetLanguage
        ));

        if ($dryRun) {
            $totalChars = 0;
            foreach ($attributes as $attributeCode) {
                $value = (string)$defaultProduct->getData($attributeCode);
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

        $storeProduct = $this->productRepository->getById($productId, false, $targetStoreId);

        if (!$force && !$dryRun) {
            $attributesToTranslate = [];
            $skippedAttributes = [];
            
            foreach ($attributes as $attributeCode) {
                $targetValue = $storeProduct->getData($attributeCode);
                
                if (empty(trim((string)$targetValue))) {
                    $attributesToTranslate[] = $attributeCode;
                } else {
                    $skippedAttributes[] = $attributeCode;
                }
            }
            
            foreach ($skippedAttributes as $attributeCode) {
                $output->writeln(sprintf('  - %s: skipped (already translated)', $attributeCode));
            }
            
            if (empty($attributesToTranslate)) {
                $output->writeln('  All attributes already translated. Skipping product.');
                return;
            }
            
            $attributes = $attributesToTranslate;
        }

        foreach ($attributes as $attributeCode) {
            $attributeValue = $defaultProduct->getData($attributeCode);
            if (is_array($attributeValue)) {
                $output->writeln(sprintf('  - %s is an array', $attributeCode));
                continue;
            }

            $sourceValue = (string)$attributeValue;
            if (empty(trim($sourceValue))) {
                continue;
            }

            $translatedValue = $this->translationService->translate($sourceValue, $sourceLanguage, $targetLanguage);
            $storeProduct->setData($attributeCode, $translatedValue);
            $output->writeln(sprintf('  - %s: translated', $attributeCode));
        }

        $storeProduct->setStoreId($targetStoreId);
        $this->productRepository->save($storeProduct);
        $output->writeln(sprintf('  Product %d saved.', $productId));
    }
}
