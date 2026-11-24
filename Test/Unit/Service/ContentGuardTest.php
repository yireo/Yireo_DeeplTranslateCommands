<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Unit\Service;

use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validator\HTML\WYSIWYGValidatorInterface;
use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Exception\UnsafeTranslationException;
use Yireo\DeeplTranslateCommands\Service\ContentGuard;

class ContentGuardTest extends TestCase
{
    public function testProtectAndRestoreDirectives(): void
    {
        $guard = $this->createGuard();
        $source = '<p>Hello {{var name}}</p><img src="{{media url="a.png"}}"/>{{widget type="Foo" id="1"}}';

        [$protected, $directives] = $guard->protectDirectives($source);

        $this->assertSame(['{{var name}}', '{{widget type="Foo" id="1"}}'], $directives);
        $this->assertStringContainsString('<x-keep id="0"></x-keep>', $protected);
        $this->assertStringContainsString('<x-keep id="1"></x-keep>', $protected);
        $this->assertStringContainsString('src="{{media url="a.png"}}"', $protected);
        $this->assertSame($source, $guard->restoreDirectives($protected, $directives));
    }

    public function testRestoreAcceptsSelfClosingPlaceholder(): void
    {
        $this->assertSame(
            'Hallo {{var name}}',
            $this->createGuard()->restoreDirectives('Hallo <x-keep id="0"/>', ['{{var name}}'])
        );
    }

    public function testRestoreRejectsUnknownPlaceholder(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->createGuard()->restoreDirectives('Hallo <x-keep id="5"></x-keep>', ['{{var name}}']);
    }

    public function testRestoreRejectsMalformedPlaceholder(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->createGuard()->restoreDirectives('Hallo <x-keep id="0">foo</x-keep>', ['{{var name}}']);
    }

    public function testAcceptsSafeTranslation(): void
    {
        $this->createGuard()->assertSafe(
            '<p class="a">Hello <a href="/x?a=b">world</a> {{var name}}</p>',
            '<p class="a">Hallo <a href="/x?a=b">Welt</a> {{var name}}</p>',
            true
        );
        $this->addToAssertionCount(1);
    }

    public function testRejectsChangedDirective(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->createGuard()->assertSafe('{{block id="footer"}}', '{{block id="admin"}}', true);
    }

    public function testRejectsRemovedDirective(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->createGuard()->assertSafe('Hello {{var name}}', 'Hallo', false);
    }

    public function testRejectsNewTag(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->expectExceptionMessage('script');
        $this->createGuard()->assertSafe('<p>Hello</p>', '<p>Hallo</p><script>alert(1)</script>', true);
    }

    public function testRejectsNewTagInPlainText(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->createGuard()->assertSafe('Hello', 'Hallo <img src=x>', false);
    }

    public function testRejectsNewAttribute(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->expectExceptionMessage('onclick');
        $this->createGuard()->assertSafe('<a href="/x">Hello</a>', '<a href="/x" onclick="steal()">Hallo</a>', true);
    }

    public function testRejectsNewScriptUrl(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->createGuard()->assertSafe('<a href="/x">Hello</a>', '<a href="javascript:steal()">Hallo</a>', true);
    }

    public function testRejectsRestrictedHtmlWhenSourceIsValid(): void
    {
        $validator = $this->createMock(WYSIWYGValidatorInterface::class);
        $validator->method('validate')->willReturnCallback(function (string $html): void {
            if (str_contains($html, 'Hallo')) {
                throw new ValidationException(__('Restricted'));
            }
        });

        $this->expectException(UnsafeTranslationException::class);
        (new ContentGuard($validator))->assertSafe('<p>Hello</p>', '<p>Hallo</p>', true);
    }

    public function testSkipsHtmlValidationWhenSourceIsAlreadyInvalid(): void
    {
        $validator = $this->createMock(WYSIWYGValidatorInterface::class);
        $validator->method('validate')->willThrowException(new ValidationException(__('Restricted')));

        (new ContentGuard($validator))->assertSafe('<p>Hello</p>', '<p>Hallo</p>', true);
        $this->addToAssertionCount(1);
    }

    private function createGuard(): ContentGuard
    {
        return new ContentGuard($this->createMock(WYSIWYGValidatorInterface::class));
    }
}
