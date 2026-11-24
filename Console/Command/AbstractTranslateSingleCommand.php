<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Service\TranslationTarget;

abstract class AbstractTranslateSingleCommand extends AbstractTranslateCommand
{
    abstract protected function getEntityLabel(): string;

    abstract protected function translateEntity(
        int $entityId,
        TranslationTarget $target,
        bool $dryRun,
        OutputInterface $output,
        bool $force
    ): void;

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, $this->getEntityLabel() . ' ID');
        $this->addArgument('store_code', InputArgument::REQUIRED, 'Target store view code');
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show what would be translated without making changes'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force re-translation even if translations already exist'
        );
    }

    protected function validateInput(InputInterface $input): ?string
    {
        $id = filter_var($input->getArgument('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return sprintf('%s ID must be a positive integer.', $this->getEntityLabel());
        }

        return null;
    }

    protected function executeTranslation(
        InputInterface $input,
        OutputInterface $output,
        TranslationTarget $target
    ): int {
        $this->translateEntity(
            (int)$input->getArgument('id'),
            $target,
            (bool)$input->getOption('dry-run'),
            $output,
            (bool)$input->getOption('force')
        );

        return Command::SUCCESS;
    }
}
