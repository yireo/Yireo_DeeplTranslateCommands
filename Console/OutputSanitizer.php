<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;

class OutputSanitizer
{
    public function sanitize(string $text): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}]/u', '', $text);
        if ($clean === null) {
            $clean = (string)preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        }

        return OutputFormatter::escape($clean);
    }
}
