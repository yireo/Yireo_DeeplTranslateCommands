# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 27 November 2026
### Added
- Console commands to translate products, categories, CMS pages and CMS blocks to another store view with DeepL (`deepl:translate:product`, `:products`, `:category`, `:categories`, `:cms-page`, `:cms-pages`, `:cms-block`, `:cms-blocks`)
- A confirmation prompt and a `--max-chars` limit for the bulk commands
- Existing translations are detected, and identical ones can be overridden

### Changed
- Requires PHP 8.2 or newer (PHP 8.5 is supported) and deepl-php 1.11.1 or newer
- Only store-scoped attributes are translated, never sku, url_key, layout attributes or array values, and only translated attributes are saved for the target store
- The DeepL API key is stored as sensitive configuration

### Fixed
- Translations that add HTML tags, attributes or script URLs, change `{{directives}}`, or change URL or style attribute values are rejected
- The admin store, inactive stores and target stores with the same language are refused, and a CMS entity that is the source or is assigned to several stores is never overwritten
- Translating is refused in single store mode, where Magento would save the values into the default scope
- `setup:install` no longer fails because the console commands need the database before it exists
- A failing item no longer stops a bulk run, and console output is escaped
