<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Service\LocaleMapper;
use Yireo\DeeplTranslateCommands\Translator\ProductTranslator;

class TranslateProductsCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LocaleMapper $localeMapper,
        private readonly ProductTranslator $productTranslator,
        private readonly ProductCollectionFactory $productCollectionFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('deepl:translate:products');
        $this->setDescription('Translate all products into a specific store view');
        $this->addArgument('store_code', InputArgument::REQUIRED, 'Target store view code');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be translated without making changes');
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of products to process per batch', '50');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force re-translation even if translations already exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
        }

        if (!$this->config->isEnabled()) {
            $output->writeln('<error>DeepL Translate is disabled. Enable it in Stores > Configuration > Yireo > DeepL Translate.</error>');
            return Command::FAILURE;
        }

        $storeCode = (string)$input->getArgument('store_code');
        $dryRun = (bool)$input->getOption('dry-run');
        $batchSize = (int)$input->getOption('batch-size');
        $force = (bool)$input->getOption('force');

        try {
            $store = $this->storeRepository->get($storeCode);
            $targetStoreId = (int)$store->getId();

            $sourceLocale = (string)$this->scopeConfig->getValue('general/locale/code');
            $targetLocale = (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORES, $targetStoreId);

            $sourceLanguage = $this->localeMapper->mapLocaleToDeeplSourceLanguage($sourceLocale);
            $targetLanguage = $this->localeMapper->mapLocaleToDeeplLanguage($targetLocale);

            $output->writeln(sprintf('Source locale: %s (%s), Target locale: %s (%s)', $sourceLocale, $sourceLanguage, $targetLocale, $targetLanguage));
            $output->writeln(sprintf('Batch size: %d', $batchSize));

            $collection = $this->productCollectionFactory->create();
            $productIds = $collection->getAllIds();
            $total = count($productIds);

            $output->writeln(sprintf('Found %d products to translate.', $total));

            $failed = 0;
            foreach ($productIds as $index => $productId) {
                $current = $index + 1;
                $output->writeln(sprintf('[%d/%d]', $current, $total));

                try {
                    $this->productTranslator->translate((int)$productId, $targetStoreId, $sourceLanguage, $targetLanguage, $dryRun, $output, $force);
                } catch (\Exception $e) {
                    $output->writeln(sprintf('  <error>Failed: %s</error>', $e->getMessage()));
                    $failed++;
                }

                if ($current % $batchSize === 0) {
                    $output->writeln(sprintf('--- Batch of %d completed ---', $batchSize));
                }
            }

            $output->writeln(sprintf('Translation complete. %d/%d products processed. %d failed.', $total - $failed, $total, $failed));
            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
