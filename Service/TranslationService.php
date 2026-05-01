<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

use DeepL\DeepLException;
use Magento\Framework\Exception\LocalizedException;

class TranslationService
{
    public function __construct(
        private readonly DeeplClientFactory $deeplClientFactory,
    ) {
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $text = trim($text);
        if (empty($text)) {
            return '';
        }

        try {
            $client = $this->deeplClientFactory->create();
            $result = $client->translateText(
                $text,
                $sourceLanguage,
                $targetLanguage,
                ['tag_handling' => 'html']
            );

            return $result->text;
        } catch (DeepLException $e) {
            throw new LocalizedException(__(
                'DeepL translation failed: %1',
                $e->getMessage()
            ), $e);
        }
    }
}
