<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Unit\Translator;

use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Yireo\DeeplTranslateCommands\Console\OutputSanitizer;
use Yireo\DeeplTranslateCommands\Translator\FieldSelector;

class FieldSelectorTest extends TestCase
{
    public function testSelectsUntranslatedFields(): void
    {
        $source = new DataObject(['name' => 'Shirt', 'description' => '<p>Nice</p>', 'meta_title' => 'Shirt', 'empty' => ' ']);
        $target = new DataObject(['name' => 'Hemd', 'description' => '<p>Nice</p>', 'meta_title' => '']);
        $output = new BufferedOutput();

        $selected = (new FieldSelector(new OutputSanitizer()))->select(
            ['name' => false, 'description' => true, 'meta_title' => false, 'empty' => false],
            $source,
            $target,
            false,
            $output
        );

        $this->assertSame(['description' => true, 'meta_title' => false], $selected);
        $this->assertStringContainsString('name: skipped (already translated)', $output->fetch());
    }

    public function testForceSelectsAllNonEmptyFields(): void
    {
        $source = new DataObject(['name' => 'Shirt', 'description' => '']);
        $target = new DataObject(['name' => 'Hemd']);

        $selected = (new FieldSelector(new OutputSanitizer()))->select(
            ['name' => false, 'description' => true],
            $source,
            $target,
            true,
            new BufferedOutput()
        );

        $this->assertSame(['name' => false], $selected);
    }

    public function testSkipsNonScalarValues(): void
    {
        $output = new BufferedOutput();
        $selected = (new FieldSelector(new OutputSanitizer()))->select(
            ['options' => false],
            new DataObject(['options' => ['a']]),
            null,
            false,
            $output
        );

        $this->assertSame([], $selected);
        $this->assertStringContainsString('options: skipped (not a string)', $output->fetch());
    }

    public function testReportDryRun(): void
    {
        $output = new BufferedOutput();
        (new FieldSelector(new OutputSanitizer()))->reportDryRun(
            ['name' => false, 'description' => true],
            new DataObject(['name' => 'Grün', 'description' => 'abc']),
            $output
        );

        $text = $output->fetch();
        $this->assertStringContainsString('name: 4 chars', $text);
        $this->assertStringContainsString('Total characters: 7', $text);
    }
}
