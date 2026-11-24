<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\AttributeFilter;
use Yireo\DeeplTranslateCommands\Service\TranslationService;

class ProductTranslator
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAction $productAction,
        private readonly AttributeFilter $attributeFilter,
        private readonly FieldSelector $fieldSelector,
        private readonly TranslationService $translationService,
        private readonly Config $config,
        private readonly OutputSanitizer $outputSanitizer,
        private readonly StoreManagerInterface $storeManager,
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
        if ($targetStoreId === Store::DEFAULT_STORE_ID) {
            throw new LocalizedException(__('The admin store view can not be used as translation target.'));
        }

        if ($this->storeManager->hasSingleStore()) {
            throw new LocalizedException(__('Translating requires multiple store views.'));
        }

        $attributes = $this->attributeFilter->filter(Product::ENTITY, $this->config->getProductAttributes());
        if (empty($attributes)) {
            throw new LocalizedException(__('No translatable product attributes configured for translation.'));
        }

        $defaultProduct = $this->productRepository->getById($productId, false, Store::DEFAULT_STORE_ID);
        $storeProduct = $this->productRepository->getById($productId, false, $targetStoreId);
        if (!$defaultProduct instanceof Product || !$storeProduct instanceof Product) {
            throw new LocalizedException(__('Unable to load product %1.', $productId));
        }

        $output->writeln(sprintf(
            '%sTranslating product "%s" (ID: %d) [%s → %s]',
            $dryRun ? '[DRY RUN] Would translate: ' : '',
            $this->outputSanitizer->sanitize((string)($defaultProduct->getName() ?: 'Unknown')),
            $productId,
            $this->outputSanitizer->sanitize($sourceLanguage),
            $this->outputSanitizer->sanitize($targetLanguage)
        ));

        $attributes = $this->fieldSelector->select($attributes, $defaultProduct, $storeProduct, $force, $output);

        if ($dryRun) {
            $this->fieldSelector->reportDryRun($attributes, $defaultProduct, $output);
            return;
        }

        if (empty($attributes)) {
            $output->writeln('  All attributes already translated. Skipping product.');
            return;
        }

        $translations = [];
        foreach ($attributes as $attributeCode => $isHtml) {
            $translations[$attributeCode] = $this->translationService->translate(
                (string)$defaultProduct->getData($attributeCode),
                $sourceLanguage,
                $targetLanguage,
                $isHtml
            );
            $output->writeln(sprintf('  - %s: translated', $this->outputSanitizer->sanitize($attributeCode)));
        }

        $this->productAction->updateAttributes([$productId], $translations, $targetStoreId);
        $output->writeln(sprintf('  Product %d saved.', $productId));
    }
}
