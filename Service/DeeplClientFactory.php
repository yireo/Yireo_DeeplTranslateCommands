<?php
declare(strict_types=1);

namespace Yireo\DeeplTranslateCommands\Service;

use DeepL\DeepLClient;
use Magento\Framework\Exception\LocalizedException;
use Yireo\DeeplTranslateCommands\Config\Config;

class DeeplClientFactory
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function create(): DeepLClient
    {
        $apiKey = $this->config->getApiKey();
        if (empty($apiKey)) {
            throw new LocalizedException(__(
                'DeepL API key is not configured. Please set it in Stores > Configuration > Yireo > DeepL Translate.'
            ));
        }

        return new DeepLClient($apiKey, [
            'app_info' => new \DeepL\AppInfo('yireo-deepl-translate-commands', '1.0.0'),
        ]);
    }
}
