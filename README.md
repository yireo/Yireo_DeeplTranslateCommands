# Yireo_DeeplTranslateCommands

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-deepl-translate-commands)
<!-- badges.specs.end -->


> This Magento 2 module adds various CLI commands to allow categoriies, products, CMS pages and CMS blocks to be translated via DeepL

### Installation
Install this package via composer:
```bash
composer require yireo/magento2-deepl-translate-commands
```

Next, enable this module:
```bash
bin/magento module:enable Yireo_DeeplTranslateCommands
```

### Usage
```bash
bin/magento deepl:translate:product 1 german --dry-run
bin/magento deepl:translate:products german --max-chars=100000
```

Notes:
- The target store view may not be the `admin` store view, and its locale must differ from the default locale. A regional variant (for example `en_US` to `en_GB`) is allowed.
- Translating needs at least two store views: with a single store view Magento saves store view values into the default scope.
- Only store-view scoped attributes can be translated. `sku`, `url_key`, `url_path` and the layout attributes are never translated.
- Only the translated attributes are saved for the target store view. Other attributes keep using the default values.
- A translation is rejected when DeepL returns HTML tags, attributes, URL or style values or `{{directives}}` that are not in the source.
- A CMS page or block is not overwritten when it is the source itself or is assigned to multiple store views.
- The bulk commands ask for confirmation. Use `--no-interaction` (`-n`) in scripts. Use `--max-chars` to cap the DeepL quota used.
## Current status

<!-- badges.test.start -->
![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_DeeplTranslateCommands/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_DeeplTranslateCommands/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_DeeplTranslateCommands/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_DeeplTranslateCommands/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_DeeplTranslateCommands/compile.yml?label=compile)
<!-- badges.test.end -->
