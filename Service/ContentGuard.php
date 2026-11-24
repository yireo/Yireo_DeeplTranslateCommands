<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

use Magento\Framework\Validator\HTML\WYSIWYGValidatorInterface;
use Throwable;
use Yireo\DeeplTranslateCommands\Exception\UnsafeTranslationException;

class ContentGuard
{
    public const PLACEHOLDER_TAG = 'x-keep';

    private const DIRECTIVE_PATTERN = '/\{\{.*?\}\}/s';
    private const TAG_OR_DIRECTIVE_PATTERN = '/<[^>]*>|\{\{.*?\}\}/s';
    private const PLACEHOLDER_PATTERN = '/<x-keep\s+id="(\d+)"\s*(?:\/>|>\s*<\/x-keep>)/i';
    private const TAG_PATTERN = '/<\s*\/?\s*([a-z][a-z0-9:-]*)([^>]*)>/i';
    private const ATTRIBUTE_PATTERN = '/([a-z_:@][-a-z0-9_:.@]*)\s*=/i';
    private const QUOTED_VALUE_PATTERN = '/"[^"]*"|\'[^\']*\'/';
    private const URL_ATTRIBUTE_PATTERN =
        '/\\s(href|src|srcset|action|formaction|poster|data|xlink:href|background|cite|style)\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)/i';
    private const DANGEROUS_PATTERN = '/javascript\s*:|vbscript\s*:|data\s*:\s*text\/html/i';

    public function __construct(
        private readonly WYSIWYGValidatorInterface $wysiwygValidator,
    ) {
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    public function protectDirectives(string $text): array
    {
        $directives = [];
        $protected = preg_replace_callback(
            self::TAG_OR_DIRECTIVE_PATTERN,
            function (array $match) use (&$directives): string {
                if (str_starts_with($match[0], '<')) {
                    return $match[0];
                }

                $directives[] = $match[0];
                return sprintf('<%s id="%d"></%s>', self::PLACEHOLDER_TAG, count($directives) - 1, self::PLACEHOLDER_TAG);
            },
            $text
        );

        return [(string)$protected, $directives];
    }

    /**
     * @param string[] $directives
     */
    public function restoreDirectives(string $text, array $directives): string
    {
        $restored = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function (array $match) use ($directives): string {
                $index = (int)$match[1];
                if (!isset($directives[$index])) {
                    throw new UnsafeTranslationException(__('Translation contains an unknown directive placeholder.'));
                }

                return $directives[$index];
            },
            $text
        );

        if ($restored === null || stripos($restored, '<' . self::PLACEHOLDER_TAG) !== false) {
            throw new UnsafeTranslationException(__('Translation contains malformed directive placeholders.'));
        }

        return $restored;
    }

    public function assertSafe(string $source, string $translation, bool $isHtml): void
    {
        if ($this->getDirectives($source) !== $this->getDirectives($translation)) {
            throw new UnsafeTranslationException(__('Translation changed, added or removed Magento directives.'));
        }

        $sourceMarkup = $this->getMarkup($source);
        $translationMarkup = $this->getMarkup($translation);

        $newTags = array_diff($translationMarkup['tags'], $sourceMarkup['tags']);
        if (!empty($newTags)) {
            throw new UnsafeTranslationException(__(
                'Translation contains HTML tags that are not in the source: %1',
                implode(', ', $newTags)
            ));
        }

        $newAttributes = array_diff($translationMarkup['attributes'], $sourceMarkup['attributes']);
        if (!empty($newAttributes)) {
            throw new UnsafeTranslationException(__(
                'Translation contains HTML attributes that are not in the source: %1',
                implode(', ', $newAttributes)
            ));
        }

        $newUrlValues = array_diff($this->getUrlAttributeValues($translation), $this->getUrlAttributeValues($source));
        if (!empty($newUrlValues)) {
            throw new UnsafeTranslationException(__('Translation changed URL or style attribute values.'));
        }

        if (preg_match(self::DANGEROUS_PATTERN, $translation) && !preg_match(self::DANGEROUS_PATTERN, $source)) {
            throw new UnsafeTranslationException(__('Translation contains a script URL that is not in the source.'));
        }

        if ($isHtml && $this->isValidHtml($source) && !$this->isValidHtml($translation)) {
            throw new UnsafeTranslationException(__('Translation contains restricted HTML.'));
        }
    }

    /**
     * @return string[]
     */
    private function getDirectives(string $text): array
    {
        preg_match_all(self::DIRECTIVE_PATTERN, $text, $matches);
        $directives = $matches[0];
        sort($directives);

        return $directives;
    }

    /**
     * @return array{tags: string[], attributes: string[]}
     */
    private function getMarkup(string $text): array
    {
        $tags = [];
        $attributes = [];

        preg_match_all(self::TAG_PATTERN, $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $tags[] = strtolower($match[1]);
            $attributeString = (string)preg_replace(self::QUOTED_VALUE_PATTERN, '', $match[2]);
            preg_match_all(self::ATTRIBUTE_PATTERN, $attributeString, $attributeMatches);
            foreach ($attributeMatches[1] as $attribute) {
                $attributes[] = strtolower($attribute);
            }
        }

        return [
            'tags' => array_values(array_unique($tags)),
            'attributes' => array_values(array_unique($attributes)),
        ];
    }

    /**
     * @return string[]
     */
    private function getUrlAttributeValues(string $text): array
    {
        $values = [];
        preg_match_all(self::TAG_PATTERN, $text, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            preg_match_all(self::URL_ATTRIBUTE_PATTERN, ' ' . $tag[2], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $value = trim($match[2], '"\'');
                $values[] = strtolower($match[1]) . '=' . $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function isValidHtml(string $html): bool
    {
        try {
            $this->wysiwygValidator->validate($html);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
