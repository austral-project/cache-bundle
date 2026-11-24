# Changelog

All notable changes to this bundle are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). All Austral bundles share the same major and minor version.

## [Unreleased]

The HTTP cache of 3.1 is removed. The bundle is being rewritten for 4.0 as a render cache (HTML of the content blocks, invalidated by tags when an entity is saved). Until then, it only defines its configuration.

### Added

- `tests/DependencyInjection/ContainerTest.php`: compiles the container, creates the configuration service, and checks the public alias, the default value of `enabled` and the rejection of the 3.1 options.

### Changed

- Sources moved to `src/` (PSR-4 `Austral\CacheBundle\` → `src/`).
- **BREAKING** Requires PHP 8.4 and Symfony 7.4.
- **BREAKING** `enabled` defaults to `false`. In 3.1, the default of the configuration tree (`true`) overrode the declared default (`false`), so the cache was enabled when the option was not set.
- **BREAKING** Services are defined explicitly (no autowiring, no autoconfiguration). The configuration service id is `Austral\CacheBundle\Configuration\CacheConfiguration` (private); `austral.cache.config` is a public alias. In 3.1, `austral.cache.config` was the service id and the class name was an alias.
- **BREAKING** The XML namespace of the bundle configuration is `https://austral.dev/schema/dic/austral_cache` (it was `https://austral.app/schema/dic/austral_cache`).
- The extension no longer reads `config/bundles.php` of the application.
- The bundle no longer suggests admin-bundle and website-bundle: the rewritten cache will not depend on them.

### Removed

- **BREAKING** The HTTP cache:
  - `Services\HttpCache` (extension of the `HttpCache` reverse proxy of FrameworkBundle) and the `http_cache` service that the bundle defined in place of the one of FrameworkBundle;
  - `Services\HttpCacheEnabledChecker` (enabling by domain and URL, `setEnabledByUrl()`, `getSharedMaxAge()`);
  - `Listener\CacheListener`, which set the shared max age on the responses (`kernel.response`);
  - `EventSubscriber\HttpCacheSubscriber` and `Event\HttpCacheEvent` (`austral.cache.clear_http_cache`).
- **BREAKING** The `austral.cache.http_cache`, `austral.cache.http_cache_enabled_checker`, `austral.cache.listener` and `austral.cache.event_subscriber` services.
- **BREAKING** The `clearAuto`, `shared_age_max`, `include` and `exclude` options: only `enabled` remains.
- **BREAKING** The administration screen of the cache (`Admin\CacheAdmin`, `cache` module with its `index` and `purge-all` actions, its template and its translations).

## [3.1.0] - 2023-09-01

- Created new repository and new branch in to Github
- Changed the project License to the MIT
- Add to Packagist Service for installation with composer

[Unreleased]: https://github.com/austral-project/cache-bundle/compare/v3.1.1...4.x
[3.1.0]: https://github.com/austral-project/cache-bundle/releases/tag/v3.1.0
