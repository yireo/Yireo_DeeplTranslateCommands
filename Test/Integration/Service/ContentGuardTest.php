<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Integration\Service;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Exception\UnsafeTranslationException;
use Yireo\DeeplTranslateCommands\Service\ContentGuard;

class ContentGuardTest extends TestCase
{
    public function testAcceptsSafeHtml(): void
    {
        $this->getGuard()->assertSafe('<p>Hello <strong>world</strong></p>', '<p>Hallo <strong>Welt</strong></p>', true);
        $this->addToAssertionCount(1);
    }

    /**
     * @magentoConfigFixture default/cms/wysiwyg/force_valid 0
     */
    public function testRejectsScriptEvenWhenWysiwygValidationIsNotForced(): void
    {
        $this->expectException(UnsafeTranslationException::class);
        $this->getGuard()->assertSafe('<p>Hello</p>', '<p>Hallo</p><script>alert(1)</script>', true);
    }

    private function getGuard(): ContentGuard
    {
        return Bootstrap::getObjectManager()->get(ContentGuard::class);
    }
}
