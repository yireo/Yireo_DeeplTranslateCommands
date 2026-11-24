<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\State;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\TargetStoreResolver;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Service\TranslationTarget;
use Yireo\DeeplTranslateCommands\Translator\CategoryTranslator;

class TranslateCategoriesCommand extends AbstractTranslateAllCommand
{
    public function __construct(
        State $appState,
        Config $config,
        TargetStoreResolver $targetStoreResolver,
        OutputSanitizer $outputSanitizer,
        TranslationService $translationService,
        private readonly CategoryTranslator $categoryTranslator,
        private readonly CategoryCollectionFactory $collectionFactory,
        ?string $name = null
    ) {
        parent::__construct($appState, $config, $targetStoreResolver, $outputSanitizer, $translationService, $name);
    }

    protected function configure(): void
    {
        $this->setName('deepl:translate:categories');
        $this->setDescription('Translate all categories into a specific store view');
        parent::configure();
    }

    protected function getEntityLabel(): string
    {
        return 'categories';
    }

    protected function getEntityIds(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToFilter('level', ['gt' => 1]);

        return $collection->getAllIds();
    }

    protected function translateEntity(
        int $entityId,
        TranslationTarget $target,
        bool $dryRun,
        OutputInterface $output,
        bool $force
    ): void {
        $this->categoryTranslator->translate(
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
