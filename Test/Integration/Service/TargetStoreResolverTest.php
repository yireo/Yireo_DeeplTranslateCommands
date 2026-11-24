<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Test\Integration\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\DeeplTranslateCommands\Service\TargetStoreResolver;

class TargetStoreResolverTest extends TestCase
{
    public function testRejectsAdminStore(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('admin store view');
        $this->getResolver()->resolve('admin');
    }

    /**
     * @magentoConfigFixture default/general/locale/code en_US
     * @magentoConfigFixture default_store general/locale/code en_US
     */
    public function testRejectsStoreWithSameLocale(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('same language');
        $this->getResolver()->resolve('default');
    }

    /**
     * @magentoDataFixture Magento/Store/_files/core_fixturestore.php
     * @magentoConfigFixture default/general/locale/code en_US
     * @magentoConfigFixture fixturestore_store general/locale/code de_DE
     */
    public function testResolvesStoreWithOtherLocale(): void
    {
        $target = $this->getResolver()->resolve('fixturestore');

        $storeId = (int)Bootstrap::getObjectManager()->get(StoreRepositoryInterface::class)->get('fixturestore')->getId();
        $this->assertSame($storeId, $target->storeId);
        $this->assertSame('EN', $target->sourceLanguage);
        $this->assertSame('DE', $target->targetLanguage);
    }

    private function getResolver(): TargetStoreResolver
    {
        return Bootstrap::getObjectManager()->get(TargetStoreResolver::class);
    }
}
