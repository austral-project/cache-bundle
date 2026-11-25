# Changelog

All notable changes to this bundle are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). All Austral bundles share the same major and minor version.

## [Unreleased]

The HTTP cache of 3.1 is removed. The bundle is rewritten as the cache infrastructure of Austral: a Doctrine result cache and a cache of HTML renders, both invalidated when an entity is saved. It knows no feature of Austral: the bundles that use it (domain, content-block…) declare what they cache.

### Added

- Configuration `austral_cache`: `enabled`, `adapter` (`filesystem`, `redis` or `pool`), `dsn`, `pool`, `ttl`. With the filesystem and redis adapters, two pools are declared in FrameworkBundle: `austral_cache.result` and `austral_cache.render` (tag aware). With the pool adapter, a pool of the application is used.
- `Cache\ResultCache` (`austral.cache.result`): Doctrine result cache, `apply($query, $key)`. The SQL rows are cached; the entities are hydrated and managed as usual. A bundle declares its keys and the class that invalidates them with the `austral.cache.result_key` service tag (`key`, `class`), collected by `DependencyInjection\Compiler\ResultKeyPass`.
- `Cache\RenderCache` (`austral.cache.render`): cache of HTML renders, `get($name, $id, $callback, $entities, $tags, $ttl)`. The callback is only called when the render is not in the cache. The key is the name, the id and the context given by the `Cache\CacheContextProviderInterface` services (tag `austral.cache.context_provider`, autoconfigured); the render is tagged with the entities it uses. `disable()` renders without the cache until the end of the request.
- `Cache\LocaleCacheContext`: the locale of the current request is part of the key of the renders, so that each language has its own render.
- `Cache\CacheTag`: tags `austral.entity.<class>`, `austral.entity.<class>.<id>` and `austral.render.<name>.<id>`, without the characters reserved by PSR-6.
- `Listener\EntityCacheListener`: after each flush that inserts, updates or deletes entities, invalidates their tags and deletes the result keys declared for their classes, then dispatches `Event\CacheInvalidatedEvent` (`austral.cache.invalidated`).
- `Event\CacheRebuildEvent` (`austral.cache.rebuild`), `Listener\CacheRebuildListener` and the `austral:cache:rebuild` command (`--result`, `--render`): clear the caches and let the bundles rebuild theirs.
- Tests of the container, of the caches, of the tags and of the Doctrine listener.

### Changed

- Sources moved to `src/` (PSR-4 `Austral\CacheBundle\` → `src/`).
- **BREAKING** Requires PHP 8.4, Symfony 7.4 and Doctrine ORM 3.
- **BREAKING** Services are defined explicitly (no autowiring, no autoconfiguration). The service ids are the class names and are private; `austral.cache.config`, `austral.cache.result` and `austral.cache.render` are public aliases. In 3.1, `austral.cache.config` was the service id and the class name was an alias.
- **BREAKING** The XML namespace of the bundle configuration is `https://austral.dev/schema/dic/austral_cache` (it was `https://austral.app/schema/dic/austral_cache`).
- The extension no longer reads `config/bundles.php` of the application.
- The bundle no longer suggests admin-bundle and website-bundle: it depends on no feature of Austral.

### Removed

- **BREAKING** The HTTP cache:
  - `Services\HttpCache` (extension of the `HttpCache` reverse proxy of FrameworkBundle) and the `http_cache` service that the bundle defined in place of the one of FrameworkBundle;
  - `Services\HttpCacheEnabledChecker` (enabling by domain and URL, `setEnabledByUrl()`, `getSharedMaxAge()`);
  - `Listener\CacheListener`, which set the shared max age on the responses (`kernel.response`);
  - `EventSubscriber\HttpCacheSubscriber` and `Event\HttpCacheEvent` (`austral.cache.clear_http_cache`).
- **BREAKING** The `austral.cache.http_cache`, `austral.cache.http_cache_enabled_checker`, `austral.cache.listener` and `austral.cache.event_subscriber` services.
- **BREAKING** The `clearAuto`, `shared_age_max`, `include` and `exclude` options.
- **BREAKING** The administration screen of the cache (`Admin\CacheAdmin`, `cache` module with its `index` and `purge-all` actions, its template and its translations). A purge screen will be provided by admin-bundle.

## [3.1.0] - 2023-09-01

- Created new repository and new branch in to Github
- Changed the project License to the MIT
- Add to Packagist Service for installation with composer

[Unreleased]: https://github.com/austral-project/cache-bundle/compare/v3.1.1...4.x
[3.1.0]: https://github.com/austral-project/cache-bundle/releases/tag/v3.1.0
