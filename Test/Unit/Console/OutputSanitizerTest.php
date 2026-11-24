<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Unit\Console;

use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;

class OutputSanitizerTest extends TestCase
{
    public function testKeepsRegularText(): void
    {
        $this->assertSame('Grüne Hose (ID 5)', (new OutputSanitizer())->sanitize('Grüne Hose (ID 5)'));
    }

    public function testEscapesConsoleFormatterTags(): void
    {
        $this->assertSame('\<info>fake\</info>', (new OutputSanitizer())->sanitize('<info>fake</info>'));
    }

    public function testStripsControlCharacters(): void
    {
        $this->assertSame(
            'Name[31mred]0;title',
            (new OutputSanitizer())->sanitize("Name\x1b[31mred\x1b]0;title\x07\r\n")
        );
    }

    public function testStripsC1ControlCharacters(): void
    {
        $this->assertSame('ab', (new OutputSanitizer())->sanitize("a\u{9B}b"));
    }

    public function testHandlesInvalidUtf8(): void
    {
        $this->assertSame("a\xFFb", (new OutputSanitizer())->sanitize("a\x1b\xFFb"));
    }
}
