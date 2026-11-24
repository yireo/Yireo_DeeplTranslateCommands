<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Integration\Console;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\DeeplTranslateCommands\Console\Command\TranslateProductCommand;
use Yireo\DeeplTranslateCommands\Console\Command\TranslateProductsCommand;

class TranslateProductsCommandTest extends TestCase
{
    /**
     * @dataProvider getInvalidOptions
     */
    public function testRejectsInvalidOptions(array $options, string $message): void
    {
        $tester = new CommandTester(Bootstrap::getObjectManager()->create(TranslateProductsCommand::class));
        $exitCode = $tester->execute(['store_code' => 'default'] + $options, ['interactive' => false]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString($message, $tester->getDisplay());
    }

    public static function getInvalidOptions(): array
    {
        return [
            [['--batch-size' => '0'], '--batch-size'],
            [['--batch-size' => 'abc'], '--batch-size'],
            [['--max-chars' => '-1'], '--max-chars'],
        ];
    }

    public function testRejectsInvalidProductId(): void
    {
        $tester = new CommandTester(Bootstrap::getObjectManager()->create(TranslateProductCommand::class));
        $exitCode = $tester->execute(['id' => 'abc', 'store_code' => 'default'], ['interactive' => false]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('positive integer', $tester->getDisplay());
    }

    /**
     * @magentoConfigFixture default/yireo_deepl_translate/general/enabled 1
     */
    public function testRejectsAdminStore(): void
    {
        $tester = new CommandTester(Bootstrap::getObjectManager()->create(TranslateProductsCommand::class));
        $exitCode = $tester->execute(['store_code' => 'admin'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('admin store view', $tester->getDisplay());
    }
}
