<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Cache;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Cache of HTML renders: if the render is in the cache it is returned, otherwise the callback renders it,
 * the render is stored and returned. The callback is the place to load the entities and render the
 * template: a cached render costs neither a Doctrine query nor a render.
 *
 * - Key: name + id + context (CacheContextProviderInterface): stable, one render per context.
 * - Tags: austral.render.<name>, austral.render.<name>.<id>, and the tag of each entity used by the render
 *   (austral.entity.<class>.<id>): saving one of these entities invalidates the render, and only the renders
 *   that use it. A render that must change when any entity of a class changes (a list) gets the tag of the
 *   class in $tags: CacheTag::forClass(Page::class).
 * - disable() renders without the cache until reset() (preview in the admin).
 *
 * Without a pool (cache disabled, or no FrameworkBundle), the callback is always called.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class RenderCache implements ResetInterface
{
  /**
   * @var bool
   */
  protected bool $disabled = false;

  /**
   * @param TagAwareCacheInterface|null             $pool
   * @param int                                     $ttl              lifetime in seconds, 0 for no expiration
   * @param iterable<CacheContextProviderInterface> $contextProviders
   */
  public function __construct(
    protected ?TagAwareCacheInterface $pool,
    protected int $ttl,
    protected iterable $contextProviders = []
  ) {
  }

  /**
   * @return bool
   */
  public function isEnabled(): bool
  {
    return null !== $this->pool && !$this->disabled;
  }

  /**
   * Renders without the cache until reset() (end of the request).
   *
   * @return $this
   */
  public function disable(): self
  {
    $this->disabled = true;

    return $this;
  }

  /**
   * @param string             $name     kind of render, e.g. "component"
   * @param string|int         $id       id of the rendered object
   * @param callable(): string $callback renders the HTML, called only when the render is not in the cache
   * @param iterable<object>   $entities entities used by the render: saving one of them invalidates it
   * @param list<string>       $tags     additional tags, e.g. CacheTag::forClass(Page::class)
   * @param int|null           $ttl      lifetime of this render, the configured lifetime by default
   *
   * @return string
   */
  public function get(string $name, string|int $id, callable $callback, iterable $entities = [], array $tags = [], ?int $ttl = null): string
  {
    if (!$this->isEnabled() || null === $this->pool)
    {
      return $callback();
    }

    $itemTags = [CacheTag::forRender($name), CacheTag::forRender($name, $id)];
    foreach ($entities as $entity)
    {
      $itemTags[] = CacheTag::ofEntity($entity);
    }
    array_push($itemTags, ...array_map(CacheTag::sanitize(...), $tags));
    $itemTags = array_values(array_unique($itemTags));
    $ttl ??= $this->ttl;

    return $this->pool->get($this->key($name, $id), static function (ItemInterface $item) use ($callback, $itemTags, $ttl): string {
      $item->tag($itemTags);
      if ($ttl > 0)
      {
        $item->expiresAfter($ttl);
      }

      return $callback();
    });
  }

  /**
   * Invalidates the renders of a kind, or of one object of this kind, in every context.
   *
   * @param string          $name
   * @param string|int|null $id
   *
   * @return bool
   */
  public function invalidate(string $name, string|int|null $id = null): bool
  {
    return $this->pool?->invalidateTags([CacheTag::forRender($name, $id)]) ?? true;
  }

  /**
   * @param list<string> $tags
   *
   * @return bool
   */
  public function invalidateTags(array $tags): bool
  {
    return [] === $tags || ($this->pool?->invalidateTags($tags) ?? true);
  }

  /**
   * @return bool
   */
  public function clear(): bool
  {
    return $this->pool instanceof \Psr\Cache\CacheItemPoolInterface ? $this->pool->clear() : true;
  }

  /**
   * Key of a render in the current context.
   *
   * @param string     $name
   * @param string|int $id
   *
   * @return string
   */
  public function key(string $name, string|int $id): string
  {
    $context = [];
    foreach ($this->contextProviders as $contextProvider)
    {
      $context += $contextProvider->getCacheContext();
    }
    ksort($context);

    return CacheTag::forRender($name, $id).([] !== $context ? '.'.hash('xxh128', serialize($context)) : '');
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void
  {
    $this->disabled = false;
  }
}
