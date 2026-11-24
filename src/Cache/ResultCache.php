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

use Doctrine\ORM\AbstractQuery;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Doctrine result cache: the SQL rows of a query are cached, the entities are hydrated and managed as usual.
 *
 * A bundle that caches a query declares its key and the class whose changes invalidate it (tag
 * austral.cache.result_key), so that EntityCacheListener deletes the key when such an entity is saved.
 * Without a pool (cache disabled, or no FrameworkBundle), every method is a no-op.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class ResultCache
{
  /**
   * @param CacheItemPoolInterface|null $pool
   * @param int                         $ttl  lifetime in seconds, 0 for no expiration
   */
  public function __construct(
    protected ?CacheItemPoolInterface $pool,
    protected int $ttl
  ) {
  }

  /**
   * @return bool
   */
  public function isEnabled(): bool
  {
    return null !== $this->pool;
  }

  /**
   * Caches the result of the query under the key.
   *
   * @param AbstractQuery $query
   * @param string        $key
   * @param int|null      $ttl   lifetime of this result, the configured lifetime by default
   *
   * @return void
   */
  public function apply(AbstractQuery $query, string $key, ?int $ttl = null): void
  {
    if (null !== $this->pool)
    {
      $query->setResultCache($this->pool)->enableResultCache($ttl ?? $this->ttl, CacheTag::sanitize($key));
    }
  }

  /**
   * @param string $key
   *
   * @return bool
   */
  public function delete(string $key): bool
  {
    return $this->pool?->deleteItem(CacheTag::sanitize($key)) ?? true;
  }

  /**
   * @param list<string> $keys
   *
   * @return bool
   */
  public function deleteMultiple(array $keys): bool
  {
    return [] === $keys || ($this->pool?->deleteItems(array_map(CacheTag::sanitize(...), $keys)) ?? true);
  }

  /**
   * @return bool
   */
  public function clear(): bool
  {
    return $this->pool?->clear() ?? true;
  }
}
