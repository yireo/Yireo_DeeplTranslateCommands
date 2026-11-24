<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Unit\Service;

use DeepL\DeepLClient;
use DeepL\TextResult;
use Magento\Framework\Validator\HTML\WYSIWYGValidatorInterface;
use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Exception\CharacterLimitReachedException;
use Yireo\DeeplTranslateCommands\Exception\UnsafeTranslationException;
use Yireo\DeeplTranslateCommands\Service\ContentGuard;
use Yireo\DeeplTranslateCommands\Service\DeeplClientFactory;
use Yireo\DeeplTranslateCommands\Service\TranslationService;

class TranslationServiceTest extends TestCase
{
    public function testHtmlTranslationProtectsDirectives(): void
    {
        $client = $this->createMock(DeepLClient::class);
        $client->expects($this->once())->method('translateText')
            ->with(
                '<p>Hello <x-keep id="0"></x-keep></p>',
                'EN',
                'DE',
                ['tag_handling' => 'html', 'ignore_tags' => ['x-keep']]
            )
            ->willReturn(new TextResult('<p>Hallo <x-keep id="0"></x-keep></p>', 'en', 10));

        $this->assertSame(
            '<p>Hallo {{var name}}</p>',
            $this->createService($client)->translate('<p>Hello {{var name}}</p>', 'EN', 'DE', true)
        );
    }

    public function testPlainTextTranslationHasNoTagHandling(): void
    {
        $client = $this->createMock(DeepLClient::class);
        $client->expects($this->once())->method('translateText')
            ->with('Shirt', 'EN', 'DE', [])
            ->willReturn(new TextResult('Hemd', 'en', 5));

        $this->assertSame('Hemd', $this->createService($client)->translate(' Shirt ', 'EN', 'DE', false));
    }

    public function testRejectsInjectedScript(): void
    {
        $client = $this->createMock(DeepLClient::class);
        $client->method('translateText')
            ->willReturn(new TextResult('<p>Hallo</p><script>alert(1)</script>', 'en', 10));

        $this->expectException(UnsafeTranslationException::class);
        $this->createService($client)->translate('<p>Hello</p>', 'EN', 'DE', true);
    }

    public function testCharacterLimit(): void
    {
        $client = $this->createMock(DeepLClient::class);
        $client->expects($this->once())->method('translateText')->willReturn(new TextResult('Hemd', 'en', 5));

        $service = $this->createService($client);
        $service->setCharacterLimit(8);
        $service->translate('Shirt', 'EN', 'DE', false);
        $this->assertSame(5, $service->getCharactersSent());

        $this->expectException(CharacterLimitReachedException::class);
        $service->translate('Trousers', 'EN', 'DE', false);
    }

    public function testEmptyTextIsNotSent(): void
    {
        $client = $this->createMock(DeepLClient::class);
        $client->expects($this->never())->method('translateText');

        $this->assertSame('', $this->createService($client)->translate(" \n", 'EN', 'DE', false));
    }

    private function createService(DeepLClient $client): TranslationService
    {
        $factory = $this->createMock(DeeplClientFactory::class);
        $factory->method('create')->willReturn($client);

        return new TranslationService(
            $factory,
            new ContentGuard($this->createMock(WYSIWYGValidatorInterface::class))
        );
    }
}
