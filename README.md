# Austral Cache

[![Version](https://img.shields.io/packagist/v/austral/cache-bundle?label=version)](https://packagist.org/packages/austral/cache-bundle)
[![CI](https://github.com/austral-project/cache-bundle/actions/workflows/ci.yml/badge.svg?branch=4.x)](https://github.com/austral-project/cache-bundle/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/austral/cache-bundle/php?version=4.x-dev)](https://packagist.org/packages/austral/cache-bundle)
[![Symfony](https://img.shields.io/badge/symfony-7.4-black)](https://symfony.com/releases/7.4)
[![License](https://img.shields.io/github/license/austral-project/cache-bundle)](LICENSE)

Cache infrastructure of Austral: a Doctrine result cache and a cache of HTML renders, both invalidated automatically when an entity is saved.

> This bundle is part of [Austral](https://github.com/austral-project), a modular back office and CMS for Symfony.

## Requirements

- PHP 8.4 or higher
- Symfony 7.4
- Doctrine ORM 3
- FrameworkBundle, to create the cache pools (filesystem or redis adapter)

## Installation

```bash
composer require austral/cache-bundle
```

If you do not use Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Austral\CacheBundle\AustralCacheBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/austral_cache.yaml
austral_cache:
    enabled: true
    adapter: filesystem      # filesystem | redis | pool
    # dsn: '%env(REDIS_URL)%'   # redis adapter
    # pool: cache.app           # pool adapter: a cache pool of your application
    ttl: 0                   # lifetime in seconds, 0 for no expiration
```

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | `true` | Disables every Austral cache when `false`. |
| `adapter` | `filesystem` | `filesystem`: one cache per server. `redis`: shared between servers. `pool`: a pool of your application. |
| `dsn` | `null` | Redis DSN, required with the `redis` adapter. |
| `pool` | `null` | Service id of a cache pool, required with the `pool` adapter. |
| `ttl` | `0` | Lifetime of the cached items. With `0`, they do not expire: they are invalidated when an entity is saved. |

With the `filesystem` and `redis` adapters, the bundle declares two pools in FrameworkBundle: `austral_cache.result` (results of Doctrine queries) and `austral_cache.render` (HTML renders). Each one can be cleared on its own:

```bash
php bin/console cache:pool:clear austral_cache.render
```

> **Several servers:** with the `filesystem` adapter, each server has its own cache, and an entity saved on one server only invalidates the cache of this server. Use the `redis` adapter, or set a `ttl`.

A bundle that uses the cache can have its own option to disable it (`austral_domain.cache`, for example). Both options must be enabled for the cache to be used.

## Usage

### Doctrine result cache

The SQL rows of a query are cached; the entities are hydrated and managed by Doctrine as usual.

```php
use Austral\CacheBundle\Cache\ResultCache;

$query = $queryBuilder->getQuery();
$resultCache->apply($query, 'app.enabled_menus');
$menus = $query->getResult();
```

Declare the key and the class whose changes invalidate it, with a tag on any service of your bundle:

```yaml
services:
  App\Repository\MenuRepository:
    tags:
      - { name: austral.cache.result_key, key: 'app.enabled_menus', class: 'App\Entity\Menu' }
```

When a `Menu` (or an entity of a subclass, or implementing the interface given as `class`) is inserted, updated or deleted, the key is deleted.

### Cache of HTML renders

```php
use Austral\CacheBundle\Cache\RenderCache;

$html = $renderCache->get('component', $componentId, function () use ($componentId): string {
    $component = $this->repository->find($componentId);   // only when the render is not in the cache

    return $this->twig->render('component.html.twig', ['component' => $component]);
}, entities: [$library, $picture]);
```

- The callback is only called when the render is not in the cache: a cached render costs neither a Doctrine query nor a render.
- The render is tagged with each entity given in `entities`: saving one of them invalidates the render, and only the renders that use it. A render that must change when any entity of a class changes (a list) gets the tag of the class: `tags: [CacheTag::forClass(Page::class)]`.
- `$renderCache->invalidate('component', $componentId)` invalidates the renders of one component, in every context; `invalidate('component')` invalidates all of them.
- `$renderCache->disable()` renders without the cache until the end of the request (preview).

### Context of the renders

The key of a render contains the context given by the `CacheContextProviderInterface` services: each context has its own render. The bundle provides the locale of the current request (`LocaleCacheContext`): each language has its own render. Other bundles add theirs, such as domain-bundle with the current domain. A service of your application that implements the interface is tagged automatically:

```php
use Austral\CacheBundle\Cache\CacheContextProviderInterface;

final class CurrentThemeContext implements CacheContextProviderInterface
{
    public function getCacheContext(): array
    {
        return ['theme' => $this->themeResolver->current()];
    }
}
```

### Invalidation and rebuild

- After each flush that inserts, updates or deletes entities, their tags are invalidated, the result keys declared for their classes are deleted, and `CacheInvalidatedEvent` (`austral.cache.invalidated`) is dispatched: a bundle can rebuild its cache right away.
- Changes made outside Doctrine (DQL bulk updates, SQL) are not seen. Rebuild the caches after them, or after a deployment:

```bash
php bin/console austral:cache:rebuild            # both caches
php bin/console austral:cache:rebuild --result   # results of Doctrine queries
php bin/console austral:cache:rebuild --render   # HTML renders
```

The command dispatches `CacheRebuildEvent` (`austral.cache.rebuild`): the pools are cleared first, then the bundles rebuild their caches. You can dispatch it from your code too.

## Documentation

The full documentation is available at https://austral.dev.

## Versions

| Bundle | Symfony | PHP | Branch |
|--------|---------|-----|---------|
| 4.x    | 7.4     | ≥ 8.4 | `4.x` |
| 3.1    | 5.4 / 6.4 | ≥ 8.0 | `3.1` (maintenance) |

See the [CHANGELOG](CHANGELOG.md) and, to upgrade from 3.1, [UPGRADE-4.0](UPGRADE-4.0.md).

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/austral-project/.github/blob/master/CONTRIBUTING.md). To report a security issue, follow the [security policy](https://github.com/austral-project/.github/blob/master/SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).

## Credits

Created by [Matthieu Beurel](https://www.mbeurel.com). Sponsored by [Yipikai Studio](https://yipikai.studio).
