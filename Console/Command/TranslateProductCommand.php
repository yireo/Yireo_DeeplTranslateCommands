<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\TargetStoreResolver;
use Yireo\DeeplTranslateCommands\Service\TranslationTarget;
use Yireo\DeeplTranslateCommands\Translator\ProductTranslator;

class TranslateProductCommand extends AbstractTranslateSingleCommand
{
    public function __construct(
        State $appState,
        Config $config,
        TargetStoreResolver $targetStoreResolver,
        OutputSanitizer $outputSanitizer,
        private readonly ProductTranslator $productTranslator,
        ?string $name = null
    ) {
        parent::__construct($appState, $config, $targetStoreResolver, $outputSanitizer, $name);
    }

    protected function configure(): void
    {
        $this->setName('deepl:translate:product');
        $this->setDescription('Translate a single product into a specific store view');
        parent::configure();
    }

    protected function getEntityLabel(): string
    {
        return 'Product';
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
