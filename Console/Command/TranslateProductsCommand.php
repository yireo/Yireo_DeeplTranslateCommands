<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\State;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\TargetStoreResolver;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Service\TranslationTarget;
use Yireo\DeeplTranslateCommands\Translator\ProductTranslator;

class TranslateProductsCommand extends AbstractTranslateAllCommand
{
    public function __construct(
        State $appState,
        Config $config,
        TargetStoreResolver $targetStoreResolver,
        OutputSanitizer $outputSanitizer,
        TranslationService $translationService,
        private readonly ProductTranslator $productTranslator,
        private readonly ProductCollectionFactory $collectionFactory,
        ?string $name = null
    ) {
        parent::__construct($appState, $config, $targetStoreResolver, $outputSanitizer, $translationService, $name);
    }

    protected function configure(): void
    {
        $this->setName('deepl:translate:products');
        $this->setDescription('Translate all products into a specific store view');
        parent::configure();
    }

    protected function getEntityLabel(): string
    {
        return 'products';
    }

    protected function getEntityIds(): array
    {
        return $this->collectionFactory->create()->getAllIds();
    }

    protected function translateEntity(
        int $entityId,
        TranslationTarget $target,
        bool $dryRun,
        OutputInterface $output,
        bool $force
    ): void {
        $this->productTranslator->translate(
            $entityId,
            $target->storeId,
            $target->sourceLanguage,
            $target->targetLanguage,
            $dryRun,
            $output,
            $force
        );
    }
}
