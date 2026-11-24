<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Austral\CacheBundle\Tests\Listener;

use Austral\CacheBundle\Cache\CacheTag;
use Austral\CacheBundle\Cache\RenderCache;
use Austral\CacheBundle\Cache\ResultCache;
use Austral\CacheBundle\Event\CacheInvalidatedEvent;
use Austral\CacheBundle\Listener\EntityCacheListener;
use Austral\CacheBundle\Tests\Fixtures\TestEntity;
use Austral\Contracts\Entity\EntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EntityCacheListenerTest extends TestCase
{
  private ArrayAdapter $resultPool;

  private RenderCache $renderCache;

  /**
   * @var list<CacheInvalidatedEvent>
   */
  private array $events = [];

  private int $renders = 0;

  protected function setUp(): void
  {
    $this->resultPool = new ArrayAdapter();
    foreach (['entity.key', 'other.key'] as $key)
    {
      $this->resultPool->save($this->resultPool->getItem($key)->set($key));
    }
    $this->renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
  }

  public function testSavingAnEntityInvalidatesTheRendersThatUseItAndTheKeysOfItsClass(): void
  {
    $this->renderCache->get('component', 1, $this->render(...), [new TestEntity('42')]);
    $this->renderCache->get('component', 2, $this->render(...), [new TestEntity('43')]);

    $this->flush(updates: [new TestEntity('42')]);

    self::assertSame('<p>3</p>', $this->renderCache->get('component', 1, $this->render(...), [new TestEntity('42')]), 'The render that uses the entity is invalidated.');
    self::assertSame('<p>2</p>', $this->renderCache->get('component', 2, $this->render(...), [new TestEntity('43')]), 'The other renders are kept.');
    self::assertFalse($this->resultPool->hasItem('entity.key'), 'The key declared for an interface of the entity is deleted.');
    self::assertTrue($this->resultPool->hasItem('other.key'), 'The key declared for another class is kept.');
  }

  public function testTheEventGivesTheClassesTheTagsAndTheKeys(): void
  {
    $this->flush(insertions: [new TestEntity()], deletions: [new TestEntity('42')]);

    self::assertCount(1, $this->events);
    $event = $this->events[0];
    self::assertSame([TestEntity::class], $event->getClasses());
    self::assertSame([CacheTag::forClass(TestEntity::class), CacheTag::forClassAndId(TestEntity::class, '42')], $event->getTags());
    self::assertSame(['entity.key'], $event->getKeys());
    self::assertTrue($event->hasClass(EntityInterface::class));
    self::assertFalse($event->hasClass(\stdClass::class));
    self::assertTrue($event->hasKey('entity.key'));
  }

  public function testANewEntityInvalidatesTheRendersTaggedWithItsClass(): void
  {
    $this->renderCache->get('menu', 'main', $this->render(...), [], [CacheTag::forClass(TestEntity::class)]);

    $this->flush(insertions: [new TestEntity()]);

    self::assertSame('<p>2</p>', $this->renderCache->get('menu', 'main', $this->render(...)));
  }

  public function testAFlushWithoutChangeDoesNothing(): void
  {
    $this->flush();

    self::assertSame([], $this->events);
    self::assertTrue($this->resultPool->hasItem('entity.key'));
  }

  public function testTheChangesAreForgottenAfterThePostFlush(): void
  {
    $listener = $this->listener();
    $entityManager = $this->entityManager(updates: [new TestEntity('42')]);
    $listener->onFlush(new OnFlushEventArgs($entityManager));
    $listener->postFlush(new PostFlushEventArgs($entityManager));
    $listener->postFlush(new PostFlushEventArgs($entityManager));

    self::assertCount(1, $this->events, 'A second flush without change dispatches nothing.');
  }

  public function testResetForgetsTheChanges(): void
  {
    $listener = $this->listener();
    $entityManager = $this->entityManager(updates: [new TestEntity('42')]);
    $listener->onFlush(new OnFlushEventArgs($entityManager));
    $listener->reset();
    $listener->postFlush(new PostFlushEventArgs($entityManager));

    self::assertSame([], $this->events);
  }

  /**
   * @param list<object> $insertions
   * @param list<object> $updates
   * @param list<object> $deletions
   */
  private function flush(array $insertions = [], array $updates = [], array $deletions = []): void
  {
    $listener = $this->listener();
    $entityManager = $this->entityManager($insertions, $updates, $deletions);
    $listener->onFlush(new OnFlushEventArgs($entityManager));
    $listener->postFlush(new PostFlushEventArgs($entityManager));
  }

  private function listener(): EntityCacheListener
  {
    $eventDispatcher = new EventDispatcher();
    $eventDispatcher->addListener(CacheInvalidatedEvent::EVENT_INVALIDATED, function (CacheInvalidatedEvent $event): void {
      $this->events[] = $event;
    });

    return new EntityCacheListener($this->renderCache, new ResultCache($this->resultPool, 0), $eventDispatcher, [
      EntityInterface::class => ['entity.key'],
      \stdClass::class => ['other.key'],
    ]);
  }

  /**
   * @param list<object> $insertions
   * @param list<object> $updates
   * @param list<object> $deletions
   */
  private function entityManager(array $insertions = [], array $updates = [], array $deletions = []): EntityManagerInterface
  {
    $unitOfWork = $this->createMock(UnitOfWork::class);
    $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
    $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
    $unitOfWork->method('getScheduledEntityDeletions')->willReturn($deletions);
    $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
    $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

    return $entityManager;
  }

  private function render(): string
  {
    ++$this->renders;

    return sprintf('<p>%d</p>', $this->renders);
  }
}
