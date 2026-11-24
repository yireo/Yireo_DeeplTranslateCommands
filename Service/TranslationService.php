<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

use DeepL\DeepLClient;
use DeepL\DeepLException;
use Magento\Framework\Exception\LocalizedException;
use Yireo\DeeplTranslateCommands\Exception\CharacterLimitReachedException;

class TranslationService
{
    private ?DeepLClient $client = null;
    private int $characterLimit = 0;
    private int $charactersSent = 0;

    public function __construct(
        private readonly DeeplClientFactory $deeplClientFactory,
        private readonly ContentGuard $contentGuard,
    ) {
    }

    public function setCharacterLimit(int $characterLimit): void
    {
        $this->characterLimit = max(0, $characterLimit);
        $this->charactersSent = 0;
    }

    public function getCharactersSent(): int
    {
        return $this->charactersSent;
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage, bool $isHtml): string
    {
        $text = trim($text, " \f\n\r\t\v\x00");
        if ($text === '') {
            return '';
        }

        $characters = mb_strlen($text);
        if ($this->characterLimit > 0 && $this->charactersSent + $characters > $this->characterLimit) {
            throw new CharacterLimitReachedException(__(
                'Character limit of %1 reached (%2 characters sent so far).',
                $this->characterLimit,
                $this->charactersSent
            ));
        }

        $directives = [];
        $options = [];
        $request = $text;
        if ($isHtml) {
            [$request, $directives] = $this->contentGuard->protectDirectives($text);
            $options = ['tag_handling' => 'html', 'ignore_tags' => [ContentGuard::PLACEHOLDER_TAG]];
        }

        try {
            $this->charactersSent += $characters;
            $result = $this->getClient()->translateText($request, $sourceLanguage, $targetLanguage, $options);
        } catch (DeepLException $e) {
            throw new LocalizedException(__('DeepL translation failed: %1', $e->getMessage()), $e);
        }

        $translation = (string)$result->text;
        if ($isHtml) {
            $translation = $this->contentGuard->restoreDirectives($translation, $directives);
        }

        $this->contentGuard->assertSafe($text, $translation, $isHtml);

        return $translation;
    }

    private function getClient(): DeepLClient
    {
        if ($this->client === null) {
            $this->client = $this->deeplClientFactory->create();
        }

        return $this->client;
    }
}
