<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Framework\App\State;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\TargetStoreResolver;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Service\TranslationTarget;
use Yireo\DeeplTranslateCommands\Translator\CmsBlockTranslator;

class TranslateCmsBlocksCommand extends AbstractTranslateAllCommand
{
    public function __construct(
        State $appState,
        Config $config,
        TargetStoreResolver $targetStoreResolver,
        OutputSanitizer $outputSanitizer,
        TranslationService $translationService,
        private readonly CmsBlockTranslator $cmsBlockTranslator,
        private readonly BlockCollectionFactory $collectionFactory,
        ?string $name = null
    ) {
        parent::__construct($appState, $config, $targetStoreResolver, $outputSanitizer, $translationService, $name);
    }

    protected function configure(): void
    {
        $this->setName('deepl:translate:cms-blocks');
        $this->setDescription('Translate all CMS blocks into a specific store view');
        parent::configure();
    }

    protected function getEntityLabel(): string
    {
        return 'CMS blocks';
    }

    protected function getEntityIds(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addStoreFilter(Store::DEFAULT_STORE_ID);

        return $collection->getAllIds();
    }

    protected function translateEntity(
        int $entityId,
        TranslationTarget $target,
        bool $dryRun,
        OutputInterface $output,
        bool $force
    ): void {
        $this->cmsBlockTranslator->translate(
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
