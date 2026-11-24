<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;
use Yireo\DeeplTranslateCommands\Config\Config;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Exception\CharacterLimitReachedException;
use Yireo\DeeplTranslateCommands\Service\TargetStoreResolver;
use Yireo\DeeplTranslateCommands\Service\TranslationService;
use Yireo\DeeplTranslateCommands\Service\TranslationTarget;

abstract class AbstractTranslateAllCommand extends AbstractTranslateCommand
{
    public function __construct(
        State $appState,
        Config $config,
        TargetStoreResolver $targetStoreResolver,
        OutputSanitizer $outputSanitizer,
        private readonly TranslationService $translationService,
        ?string $name = null
    ) {
        parent::__construct($appState, $config, $targetStoreResolver, $outputSanitizer, $name);
    }

    abstract protected function getEntityLabel(): string;

    /**
     * @return int[]|string[]
     */
    abstract protected function getEntityIds(): array;

    abstract protected function translateEntity(
        int $entityId,
        TranslationTarget $target,
        bool $dryRun,
        OutputInterface $output,
        bool $force
    ): void;

    protected function configure(): void
    {
        $label = $this->getEntityLabel();
        $this->addArgument('store_code', InputArgument::REQUIRED, 'Target store view code');
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show what would be translated without making changes'
        );
        $this->addOption(
            'batch-size',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf('Number of %s to process per batch', $label),
            '50'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force re-translation even if translations already exist'
        );
        $this->addOption(
            'max-chars',
            null,
            InputOption::VALUE_REQUIRED,
            'Stop when this many characters have been sent to DeepL (0 = no limit)',
            '0'
        );
    }

    protected function validateInput(InputInterface $input): ?string
    {
        if ($this->getIntegerOption($input, 'batch-size', 1) === null) {
            return 'The option --batch-size must be a positive integer.';
        }

        if ($this->getIntegerOption($input, 'max-chars', 0) === null) {
            return 'The option --max-chars must be zero or a positive integer.';
        }

        return null;
    }

    protected function executeTranslation(
        InputInterface $input,
        OutputInterface $output,
        TranslationTarget $target
    ): int {
        $label = $this->getEntityLabel();
        $dryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');
        $batchSize = (int)$this->getIntegerOption($input, 'batch-size', 1);
        $maxChars = (int)$this->getIntegerOption($input, 'max-chars', 0);

        $output->writeln(sprintf('Batch size: %d', $batchSize));

        $entityIds = array_values($this->getEntityIds());
        $total = count($entityIds);
        $output->writeln(sprintf('Found %d %s to translate.', $total, $label));

        if (!$dryRun && $total > 0 && !$this->confirm($input, $output, $total, $target)) {
            $output->writeln('Aborted.');
            return Command::SUCCESS;
        }

        $this->translationService->setCharacterLimit($maxChars);

        $failed = 0;
        $succeeded = 0;
        $limitReached = false;
        foreach ($entityIds as $index => $entityId) {
            $current = $index + 1;
            $output->writeln(sprintf('[%d/%d]', $current, $total));

            try {
                $this->translateEntity((int)$entityId, $target, $dryRun, $output, $force);
                $succeeded++;
            } catch (CharacterLimitReachedException $e) {
                $this->writeError($output, $e->getMessage(), '  ');
                $limitReached = true;
                break;
            } catch (Throwable $e) {
                $this->writeError($output, 'Failed: ' . $e->getMessage(), '  ');
                $failed++;
            }

            if ($current % $batchSize === 0) {
                $output->writeln(sprintf('--- Batch of %d completed ---', $batchSize));
            }
        }

        $output->writeln(sprintf(
            'Translation complete. %d/%d %s processed. %d failed.',
            $succeeded,
            $total,
            $label,
            $failed
        ));

        if (!$dryRun) {
            $output->writeln(sprintf(
                'Characters sent to DeepL: %d',
                $this->translationService->getCharactersSent()
            ));
        }

        return $failed > 0 || $limitReached ? Command::FAILURE : Command::SUCCESS;
    }

    private function confirm(InputInterface $input, OutputInterface $output, int $total, TranslationTarget $target): bool
    {
        if (!$input->isInteractive()) {
            return true;
        }

        $question = new ConfirmationQuestion(sprintf(
            'Translate %d %s into store view "%s" using the DeepL API? This uses DeepL quota. [y/N] ',
            $total,
            $this->getEntityLabel(),
            $this->outputSanitizer->sanitize($target->storeCode)
        ), false);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return (bool)$helper->ask($input, $output, $question);
    }

    private function getIntegerOption(InputInterface $input, string $name, int $minimum): ?int
    {
        $value = filter_var($input->getOption($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);

        return $value === false ? null : $value;
    }
}
