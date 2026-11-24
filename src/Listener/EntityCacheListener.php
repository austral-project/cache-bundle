<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Listener;

use Austral\CacheBundle\Cache\CacheTag;
use Austral\CacheBundle\Cache\RenderCache;
use Austral\CacheBundle\Cache\ResultCache;
use Austral\CacheBundle\Event\CacheInvalidatedEvent;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Invalidates the caches when entities are saved.
 *
 * - onFlush notes the inserted, updated and deleted entities, and the owners of the changed collections;
 * - postFlush, once the data is in the database: invalidates their tags in the render cache, deletes the
 *   result keys declared for their classes (tag austral.cache.result_key), then dispatches
 *   CacheInvalidatedEvent.
 *
 * Changes made outside Doctrine (DQL bulk updates, SQL) are not seen: use the austral:cache:rebuild command.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class EntityCacheListener implements ResetInterface
{
  /**
   * @var array<class-string, true>
   */
  protected array $classes = [];

  /**
   * @var array<string, true>
   */
  protected array $tags = [];

  /**
   * @param RenderCache                 $renderCache
   * @param ResultCache                 $resultCache
   * @param EventDispatcherInterface    $eventDispatcher
   * @param array<string, list<string>> $resultKeys      result keys by invalidating class (set by ResultKeyPass)
   */
  public function __construct(
    protected RenderCache $renderCache,
    protected ResultCache $resultCache,
    protected EventDispatcherInterface $eventDispatcher,
    protected array $resultKeys = []
  ) {
  }

  /**
   * @param OnFlushEventArgs $args
   *
   * @return void
   */
  public function onFlush(OnFlushEventArgs $args): void
  {
    $unitOfWork = $args->getObjectManager()->getUnitOfWork();
    foreach ([$unitOfWork->getScheduledEntityInsertions(), $unitOfWork->getScheduledEntityUpdates(), $unitOfWork->getScheduledEntityDeletions()] as $entities)
    {
      foreach ($entities as $entity)
      {
        $this->collect($entity);
      }
    }
    foreach ([$unitOfWork->getScheduledCollectionUpdates(), $unitOfWork->getScheduledCollectionDeletions()] as $collections)
    {
      /** @var PersistentCollection<array-key, object> $collection */
      foreach ($collections as $collection)
      {
        if ($owner = $collection->getOwner())
        {
          $this->collect($owner);
        }
      }
    }
  }

  /**
   * @param PostFlushEventArgs $args
   *
   * @return void
   */
  public function postFlush(PostFlushEventArgs $args): void
  {
    if ([] === $this->classes)
    {
      return;
    }
    $classes = array_keys($this->classes);
    $tags = array_keys($this->tags);
    $this->reset();

    $keys = $this->resultKeysOf($classes);
    $this->renderCache->invalidateTags($tags);
    $this->resultCache->deleteMultiple($keys);

    $this->eventDispatcher->dispatch(new CacheInvalidatedEvent($classes, $tags, $keys), CacheInvalidatedEvent::EVENT_INVALIDATED);
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void
  {
    $this->classes = [];
    $this->tags = [];
  }

  /**
   * @param object $entity
   *
   * @return void
   */
  protected function collect(object $entity): void
  {
    $this->classes[CacheTag::realClass($entity)] = true;
    foreach (CacheTag::forEntity($entity) as $tag)
    {
      $this->tags[$tag] = true;
    }
  }

  /**
   * Result keys declared for the classes (or a parent class, or an interface) of the changed entities.
   *
   * @param list<class-string> $classes
   *
   * @return list<string>
   */
  protected function resultKeysOf(array $classes): array
  {
    $keys = [];
    foreach ($this->resultKeys as $declaredClass => $declaredKeys)
    {
      foreach ($classes as $class)
      {
        if (is_a($class, $declaredClass, true))
        {
          array_push($keys, ...$declaredKeys);
          break;
        }
      }
    }

    return array_values(array_unique($keys));
  }
}
