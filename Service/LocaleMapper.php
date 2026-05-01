<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

use Magento\Framework\Exception\LocalizedException;

class LocaleMapper
{
    private const LOCALE_MAP = [
        'ar' => 'AR',
        'bg' => 'BG',
        'cs' => 'CS',
        'da' => 'DA',
        'de' => 'DE',
        'el' => 'EL',
        'es' => 'ES',
        'et' => 'ET',
        'fi' => 'FI',
        'fr' => 'FR',
        'hu' => 'HU',
        'id' => 'ID',
        'it' => 'IT',
        'ja' => 'JA',
        'ko' => 'KO',
        'lt' => 'LT',
        'lv' => 'LV',
        'nb' => 'NB',
        'nl' => 'NL',
        'pl' => 'PL',
        'ro' => 'RO',
        'ru' => 'RU',
        'sk' => 'SK',
        'sl' => 'SL',
        'sv' => 'SV',
        'tr' => 'TR',
        'uk' => 'UK',
    ];

    private const REGIONAL_MAP = [
        'en_US' => 'EN-US',
        'en_GB' => 'EN-GB',
        'en_AU' => 'EN-GB',
        'en_NZ' => 'EN-GB',
        'en_IE' => 'EN-GB',
        'pt_BR' => 'PT-BR',
        'pt_PT' => 'PT-PT',
        'zh_Hans_CN' => 'ZH-HANS',
        'zh_Hant_TW' => 'ZH-HANT',
    ];

    public function mapLocaleToDeeplLanguage(string $magentoLocale): string
    {
        if (isset(self::REGIONAL_MAP[$magentoLocale])) {
            return self::REGIONAL_MAP[$magentoLocale];
        }

        $parts = explode('_', $magentoLocale);
        $languageCode = strtolower($parts[0]);

        if (isset(self::LOCALE_MAP[$languageCode])) {
            return self::LOCALE_MAP[$languageCode];
        }

        if ($languageCode === 'en') {
            return 'EN-US';
        }

        if ($languageCode === 'pt') {
            return 'PT-PT';
        }

        throw new LocalizedException(__(
            'Unsupported locale "%1" for DeepL translation. Supported languages: %2',
            $magentoLocale,
            implode(', ', array_merge(array_values(self::LOCALE_MAP), array_unique(array_values(self::REGIONAL_MAP))))
        ));
    }

    public function mapLocaleToDeeplSourceLanguage(string $magentoLocale): string
    {
        $parts = explode('_', $magentoLocale);
        return strtoupper($parts[0]);
    }
}
