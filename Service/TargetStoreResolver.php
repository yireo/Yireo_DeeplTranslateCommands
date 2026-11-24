<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class TargetStoreResolver
{
    private const XML_PATH_LOCALE = 'general/locale/code';

    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LocaleMapper $localeMapper,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    public function resolve(string $storeCode): TranslationTarget
    {
        $store = $this->storeRepository->get($storeCode);
        $storeId = (int)$store->getId();

        if ($storeId === Store::DEFAULT_STORE_ID) {
            throw new LocalizedException(__(
                'The admin store view can not be used as translation target, because it holds the source content.'
            ));
        }

        if ($this->storeManager->hasSingleStore()) {
            throw new LocalizedException(__(
                'Translating requires multiple store views. With a single store view, Magento saves '
                . 'store view values into the default scope, which would overwrite the source content.'
            ));
        }

        if (!$store->getIsActive()) {
            throw new LocalizedException(__('Store view "%1" is not active.', $storeCode));
        }

        $sourceLocale = (string)$this->scopeConfig->getValue(self::XML_PATH_LOCALE);
        $targetLocale = (string)$this->scopeConfig->getValue(
            self::XML_PATH_LOCALE,
            ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $sourceLanguage = $this->localeMapper->mapLocaleToDeeplSourceLanguage($sourceLocale);
        $targetLanguage = $this->localeMapper->mapLocaleToDeeplLanguage($targetLocale);

        if ($sourceLocale === $targetLocale || $sourceLanguage === $targetLanguage) {
            throw new LocalizedException(__(
                'Store view "%1" uses the same language as the source content (%2).',
                $storeCode,
                $targetLocale
            ));
        }

        return new TranslationTarget(
            $storeId,
            (string)$store->getCode(),
            $sourceLocale,
            $sourceLanguage,
            $targetLocale,
            $targetLanguage
        );
    }
}
