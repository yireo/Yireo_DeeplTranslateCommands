<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Translator;

use Magento\Framework\DataObject;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;

class FieldSelector
{
    private const TRIM_CHARACTERS = " \f\n\r\t\v\x00";

    public function __construct(
        private readonly OutputSanitizer $outputSanitizer,
    ) {
    }

    /**
     * @param array<string, bool> $fields Field => whether the value is HTML
     * @return array<string, bool> Fields that need translation
     */
    public function select(
        array $fields,
        DataObject $source,
        ?DataObject $target,
        bool $force,
        OutputInterface $output
    ): array {
        $selected = [];
        foreach ($fields as $field => $isHtml) {
            $sourceValue = $source->getData($field);
            if ($sourceValue !== null && !is_scalar($sourceValue)) {
                $output->writeln(sprintf('  - %s: skipped (not a string)', $this->outputSanitizer->sanitize($field)));
                continue;
            }

            $sourceValue = trim((string)$sourceValue, self::TRIM_CHARACTERS);
            if ($sourceValue === '') {
                continue;
            }

            if (!$force && $target !== null) {
                $targetValue = $target->getData($field);
                $targetValue = is_scalar($targetValue) ? trim((string)$targetValue, self::TRIM_CHARACTERS) : '';
                if ($targetValue !== '' && $targetValue !== $sourceValue) {
                    $output->writeln(sprintf(
                        '  - %s: skipped (already translated)',
                        $this->outputSanitizer->sanitize($field)
                    ));
                    continue;
                }
            }

            $selected[$field] = $isHtml;
        }

        return $selected;
    }

    /**
     * @param array<string, bool> $fields
     */
    public function reportDryRun(array $fields, DataObject $source, OutputInterface $output): void
    {
        $totalChars = 0;
        foreach (array_keys($fields) as $field) {
            $charCount = mb_strlen(trim((string)$source->getData($field), self::TRIM_CHARACTERS));
            $totalChars += $charCount;
            $output->writeln(sprintf('  - %s: %d chars', $this->outputSanitizer->sanitize($field), $charCount));
        }

        $output->writeln(sprintf('  Total characters: %d', $totalChars));
    }
}
