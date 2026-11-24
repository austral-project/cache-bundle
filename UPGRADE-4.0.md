# Upgrade from 3.1 to 4.0

## Requirements

- PHP 8.4 minimum
- Symfony 7.4

## Update the dependencies

```diff
- "austral/cache-bundle": "^3.1"
+ "austral/cache-bundle": "^4.0"
```

All `austral/*` packages must be upgraded to `^4.0` at the same time.

## Breaking changes

### The HTTP cache is removed

The HTTP cache of 3.1 (an extension of the `HttpCache` reverse proxy of FrameworkBundle, enabled by domain and URL) is removed. The bundle is being rewritten for 4.0 as a render cache: the HTML of the content blocks is cached and invalidated by tags when an entity is saved. Until the rewrite is released, the bundle only defines its configuration and caches nothing.

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

Only the `enabled` option remains. It defaults to `false` and has no effect until the rewrite is released. The other options are removed, and the bundle now rejects them (`Unrecognized option "clearAuto" under "austral_cache"`):

```diff
 # config/packages/austral_cache.yaml
 austral_cache:
   enabled: false
-  clearAuto: false
-  shared_age_max: 3600
-  include:
-    path: []
-    domain: []
-  exclude:
-    path: []
-    domain: []
```

In 3.1, `enabled` was `true` when the option was not set (the default of the configuration tree overrode the declared default). In 4.0, it is `false`.

### Services: explicit definitions, private class ids

The services of the bundle are no longer autowired nor autoconfigured. The configuration is the only service left:

| Service | Id | Alias |
|---------|----|-------|
| Configuration | `Austral\CacheBundle\Configuration\CacheConfiguration` (private) | `austral.cache.config` (public) |

In 3.1, `austral.cache.config` was the service id and the class name was an alias. Fetch the configuration by its alias, or inject it:

```yaml
App\Service\MyService:
  arguments:
    $cacheConfiguration: '@austral.cache.config'
```

The XML namespace of the bundle configuration changes. In an XML configuration file:

```diff
- xmlns:austral-cache="https://austral.app/schema/dic/austral_cache"
+ xmlns:austral-cache="https://austral.dev/schema/dic/austral_cache"
```
