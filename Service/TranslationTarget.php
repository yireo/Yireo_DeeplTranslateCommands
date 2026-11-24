<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

class TranslationTarget
{
    public function __construct(
        public readonly int $storeId,
        public readonly string $storeCode,
        public readonly string $sourceLocale,
        public readonly string $sourceLanguage,
        public readonly string $targetLocale,
        public readonly string $targetLanguage,
    ) {
    }
}
