# Upgrade from 3.1 to 4.0

## Requirements

- PHP 8.4 minimum
- Symfony 7.4
- Doctrine ORM 3

## Update the dependencies

```diff
- "austral/cache-bundle": "^3.1"
+ "austral/cache-bundle": "^4.0"
```

All `austral/*` packages must be upgraded to `^4.0` at the same time.

## Breaking changes

### The HTTP cache is removed

The HTTP cache of 3.1 (an extension of the `HttpCache` reverse proxy of FrameworkBundle, enabled by domain and URL) is removed. In 4.0, the bundle is the cache infrastructure of Austral: a Doctrine result cache and a cache of HTML renders, both invalidated when an entity is saved (see the [README](README.md)).

Removed classes:

| Class | Role in 3.1 |
|-------|-------------|
| `Austral\CacheBundle\Services\HttpCache` | Reverse proxy (extends `Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache`), with `purge()` and `purgeAll()` |
| `Austral\CacheBundle\Services\HttpCacheEnabledChecker` | Enabling by domain and URL (`setEnabledByUrl()`, `checkByRequest()`, `getSharedMaxAge()`) |
| `Austral\CacheBundle\Listener\CacheListener` | Set the shared max age on the responses (`kernel.response`) |
| `Austral\CacheBundle\EventSubscriber\HttpCacheSubscriber` | Purged the cache on `HttpCacheEvent` |
| `Austral\CacheBundle\Event\HttpCacheEvent` | Event `austral.cache.clear_http_cache` |
| `Austral\CacheBundle\Admin\CacheAdmin` | Administration screen (`cache` module, `index` and `purge-all` actions) |

Removed services: `austral.cache.http_cache`, `austral.cache.http_cache_enabled_checker`, `austral.cache.listener`, `austral.cache.event_subscriber`, and `http_cache`, which the bundle defined in place of the service of FrameworkBundle.

In your application:

- Remove any use of these classes and services (`$container->get('austral.cache.http_cache')`, a listener on `HttpCacheEvent::EVENT_CLEAR_HTTP_CACHE`…).
- If `public/index.php` or your kernel wraps the kernel in `Austral\CacheBundle\Services\HttpCache`, remove it. To keep a reverse proxy, enable the one of Symfony instead (`framework.http_cache: true`): since the bundle no longer replaces the `http_cache` service, FrameworkBundle defines it again.
- Remove the cache screen from your admin menu configuration if you customised it.
- The responses no longer receive the shared max age set by the bundle.

### Configuration

The options of 3.1 are removed, and the bundle rejects them (`Unrecognized option "clearAuto" under "austral_cache"`). The new options configure where the caches are stored:

```diff
 # config/packages/austral_cache.yaml
 austral_cache:
-  enabled: false
-  clearAuto: false
-  shared_age_max: 3600
-  include:
-    path: []
-    domain: []
-  exclude:
-    path: []
-    domain: []
+  enabled: true
+  adapter: filesystem      # filesystem | redis | pool
+  # dsn: '%env(REDIS_URL)%'   # redis adapter
+  # pool: cache.app           # pool adapter
+  ttl: 0                   # 0: no expiration
```

`enabled` defaults to `true`. With `ttl: 0`, the cache does not expire: it is invalidated when an entity is saved.

> **Warning:** with the `filesystem` adapter, each server has its own cache. An entity saved on one server only invalidates the cache of this server. With several servers, use the `redis` adapter (or a shared `pool`), or set a `ttl`.

A bundle that uses the cache can have its own option to disable it (`austral_domain.cache`, for example). Both options must be enabled for the cache to be used.

### Services: explicit definitions, private class ids

The services of the bundle are no longer autowired nor autoconfigured: each one is defined with its arguments and tags. The service ids are the class names, and they are **private**:

| Service | Id | Alias |
|---------|----|-------|
| Configuration | `Austral\CacheBundle\Configuration\CacheConfiguration` | `austral.cache.config` (public) |
| Doctrine result cache | `Austral\CacheBundle\Cache\ResultCache` | `austral.cache.result` (public) |
| Cache of HTML renders | `Austral\CacheBundle\Cache\RenderCache` | `austral.cache.render` (public) |

In 3.1, `austral.cache.config` was the service id and the class name was an alias. Fetch a service by its alias, or inject it:

```yaml
App\Service\MyService:
  arguments:
    $renderCache: '@austral.cache.render'
```

The XML namespace of the bundle configuration changes. In an XML configuration file:

```diff
- xmlns:austral-cache="https://austral.app/schema/dic/austral_cache"
+ xmlns:austral-cache="https://austral.dev/schema/dic/austral_cache"
```
