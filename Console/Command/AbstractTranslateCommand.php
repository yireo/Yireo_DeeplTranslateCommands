<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Service\TargetStoreResolver;
use Yireo\DeeplTranslateCommands\Service\TranslationTarget;

abstract class AbstractTranslateCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly Config $config,
        private readonly TargetStoreResolver $targetStoreResolver,
        protected readonly OutputSanitizer $outputSanitizer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    abstract protected function executeTranslation(
        InputInterface $input,
        OutputInterface $output,
        TranslationTarget $target
    ): int;

    protected function validateInput(InputInterface $input): ?string
    {
        return null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
        }

        $inputError = $this->validateInput($input);
        if ($inputError !== null) {
            $this->writeError($output, $inputError);
            return Command::INVALID;
        }

        if (!$this->config->isEnabled()) {
            $output->writeln(
                '<error>DeepL Translate is disabled. '
                . 'Enable it in Stores > Configuration > Yireo > DeepL Translate.</error>'
            );
            return Command::FAILURE;
        }

        try {
            $target = $this->targetStoreResolver->resolve((string)$input->getArgument('store_code'));
            $output->writeln(sprintf(
                'Source locale: %s (%s), Target locale: %s (%s)',
                $this->outputSanitizer->sanitize($target->sourceLocale),
                $this->outputSanitizer->sanitize($target->sourceLanguage),
                $this->outputSanitizer->sanitize($target->targetLocale),
                $this->outputSanitizer->sanitize($target->targetLanguage)
            ));

            return $this->executeTranslation($input, $output, $target);
        } catch (Throwable $e) {
            $this->writeError($output, $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function writeError(OutputInterface $output, string $message, string $prefix = ''): void
    {
        $output->writeln(sprintf('%s<error>%s</error>', $prefix, $this->outputSanitizer->sanitize($message)));
    }
}
