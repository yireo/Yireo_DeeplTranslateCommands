<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

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
use Yireo\DeeplTranslateCommands\Translator\CmsPageTranslator;

class TranslateCmsPageCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LocaleMapper $localeMapper,
        private readonly CmsPageTranslator $cmsPageTranslator,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('deepl:translate:cms-page');
        $this->setDescription('Translate a single CMS page into a specific store view');
        $this->addArgument('id', InputArgument::REQUIRED, 'CMS Page ID');
        $this->addArgument('store_code', InputArgument::REQUIRED, 'Target store view code');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be translated without making changes');
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

        $pageId = (int)$input->getArgument('id');
        $storeCode = (string)$input->getArgument('store_code');
        $dryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');

        try {
            $store = $this->storeRepository->get($storeCode);
            $targetStoreId = (int)$store->getId();

            $sourceLocale = (string)$this->scopeConfig->getValue('general/locale/code');
            $targetLocale = (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORES, $targetStoreId);

            $sourceLanguage = $this->localeMapper->mapLocaleToDeeplSourceLanguage($sourceLocale);
            $targetLanguage = $this->localeMapper->mapLocaleToDeeplLanguage($targetLocale);

            $output->writeln(sprintf('Source locale: %s (%s), Target locale: %s (%s)', $sourceLocale, $sourceLanguage, $targetLocale, $targetLanguage));

            $this->cmsPageTranslator->translate($pageId, $targetStoreId, $sourceLanguage, $targetLanguage, $dryRun, $output, $force);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
