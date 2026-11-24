<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a flush that changed cached entities, once the tags and the result keys are invalidated.
 * A bundle can rebuild its cache right away (the domains, for example), so that the next visitor finds it ready.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class CacheInvalidatedEvent extends Event
{
  public const string EVENT_INVALIDATED = 'austral.cache.invalidated';

  /**
   * @param list<class-string> $classes classes of the changed entities
   * @param list<string>       $tags    invalidated tags
   * @param list<string>       $keys    deleted result keys
   */
  public function __construct(
    private readonly array $classes,
    private readonly array $tags,
    private readonly array $keys
  ) {
  }

  /**
   * @return list<class-string>
   */
  public function getClasses(): array
  {
    return $this->classes;
  }

  /**
   * @return list<string>
   */
  public function getTags(): array
  {
    return $this->tags;
  }

  /**
   * @return list<string>
   */
  public function getKeys(): array
  {
    return $this->keys;
  }

  /**
   * True when an entity of this class (or of a subclass, or implementing this interface) changed.
   *
   * @param class-string $class
   *
   * @return bool
   */
  public function hasClass(string $class): bool
  {
    foreach ($this->classes as $changedClass)
    {
      if (is_a($changedClass, $class, true))
      {
        return true;
      }
    }

    return false;
  }

  /**
   * @param string $key
   *
   * @return bool
   */
  public function hasKey(string $key): bool
  {
    return in_array($key, $this->keys, true);
  }
}
