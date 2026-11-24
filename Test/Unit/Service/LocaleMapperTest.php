<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Service\LocaleMapper;

class LocaleMapperTest extends TestCase
{
    public function testMapsTargetLanguages(): void
    {
        $mapper = new LocaleMapper();
        $this->assertSame('DE', $mapper->mapLocaleToDeeplLanguage('de_DE'));
        $this->assertSame('EN-GB', $mapper->mapLocaleToDeeplLanguage('en_GB'));
        $this->assertSame('EN-US', $mapper->mapLocaleToDeeplLanguage('en_CA'));
        $this->assertSame('PT-BR', $mapper->mapLocaleToDeeplLanguage('pt_BR'));
        $this->assertSame('ZH-HANS', $mapper->mapLocaleToDeeplLanguage('zh_Hans_CN'));
    }

    public function testRejectsUnsupportedTargetLanguage(): void
    {
        $this->expectException(LocalizedException::class);
        (new LocaleMapper())->mapLocaleToDeeplLanguage('xx_XX');
    }

    public function testMapsSourceLanguages(): void
    {
        $mapper = new LocaleMapper();
        $this->assertSame('EN', $mapper->mapLocaleToDeeplSourceLanguage('en_US'));
        $this->assertSame('NL', $mapper->mapLocaleToDeeplSourceLanguage('nl_NL'));
        $this->assertSame('ZH', $mapper->mapLocaleToDeeplSourceLanguage('zh_Hans_CN'));
    }

    public function testRejectsUnsupportedSourceLanguage(): void
    {
        $this->expectException(LocalizedException::class);
        (new LocaleMapper())->mapLocaleToDeeplSourceLanguage('xx_XX');
    }

    public function testRejectsEmptySourceLocale(): void
    {
        $this->expectException(LocalizedException::class);
        (new LocaleMapper())->mapLocaleToDeeplSourceLanguage('');
    }
}
