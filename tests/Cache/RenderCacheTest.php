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

namespace Austral\CacheBundle\Tests\Cache;

use Austral\CacheBundle\Cache\CacheTag;
use Austral\CacheBundle\Cache\RenderCache;
use Austral\CacheBundle\Tests\Fixtures\TestContextProvider;
use Austral\CacheBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

final class RenderCacheTest extends TestCase
{
  private int $renders = 0;

  public function testTheRenderIsStoredAndReused(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);

    $first = $renderCache->get('component', 12, $this->render(...));
    $second = $renderCache->get('component', 12, $this->render(...));

    self::assertSame('<p>1</p>', $first);
    self::assertSame($first, $second);
    self::assertSame(1, $this->renders);

    self::assertSame('<p>2</p>', $renderCache->get('component', 13, $this->render(...)), 'Another id is another render.');
  }

  public function testWithoutPoolTheCallbackIsAlwaysCalled(): void
  {
    $renderCache = new RenderCache(null, 0);

    $renderCache->get('component', 12, $this->render(...));
    $renderCache->get('component', 12, $this->render(...));

    self::assertSame(2, $this->renders);
    self::assertFalse($renderCache->isEnabled());
  }

  public function testDisableRendersWithoutTheCacheUntilReset(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
    $renderCache->get('component', 12, $this->render(...));

    $renderCache->disable();
    self::assertFalse($renderCache->isEnabled());
    self::assertSame('<p>2</p>', $renderCache->get('component', 12, $this->render(...)));

    $renderCache->reset();
    self::assertTrue($renderCache->isEnabled());
    self::assertSame('<p>1</p>', $renderCache->get('component', 12, $this->render(...)), 'The render stored before disable() is used again.');
  }

  public function testEachContextHasItsOwnRender(): void
  {
    $context = new TestContextProvider(['domain' => 'a', 'language' => 'fr']);
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0, [$context]);

    self::assertSame('<p>1</p>', $renderCache->get('component', 12, $this->render(...)));
    $context->context = ['domain' => 'b', 'language' => 'fr'];
    self::assertSame('<p>2</p>', $renderCache->get('component', 12, $this->render(...)));
    $context->context = ['language' => 'fr', 'domain' => 'a'];
    self::assertSame('<p>1</p>', $renderCache->get('component', 12, $this->render(...)), 'The order of the context does not matter.');
  }

  public function testTheKeyIsStable(): void
  {
    self::assertSame('austral.render.component.12', new RenderCache(null, 0)->key('component', 12));

    $renderCache = new RenderCache(null, 0, [new TestContextProvider(['domain' => 'a'])]);
    self::assertSame($renderCache->key('component', 12), $renderCache->key('component', 12));
    self::assertStringStartsWith('austral.render.component.12.', $renderCache->key('component', 12));
  }

  public function testAChangeOfAnEntityUsedByTheRenderInvalidatesIt(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
    $library = new TestEntity('library-1');
    $renderCache->get('component', 12, $this->render(...), [$library]);

    $renderCache->invalidateTags(CacheTag::forEntity(new TestEntity('library-2')));
    self::assertSame('<p>1</p>', $renderCache->get('component', 12, $this->render(...), [$library]));

    $renderCache->invalidateTags([CacheTag::forClassAndId(TestEntity::class, 'library-1')]);
    self::assertSame('<p>2</p>', $renderCache->get('component', 12, $this->render(...), [$library]));
  }

  /**
   * A render is tagged with its entities, not with their classes: saving one library only invalidates
   * the renders that use this library.
   */
  public function testTheClassOfAnEntityDoesNotInvalidateTheRender(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
    $renderCache->get('component', 12, $this->render(...), [new TestEntity('library-1')]);

    $renderCache->invalidateTags([CacheTag::forClass(TestEntity::class)]);

    self::assertSame('<p>1</p>', $renderCache->get('component', 12, $this->render(...), [new TestEntity('library-1')]));
  }

  public function testARenderTaggedWithAClassIsInvalidatedByAnyEntityOfThisClass(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
    $renderCache->get('menu', 'main', $this->render(...), [], [CacheTag::forClass(TestEntity::class)]);

    $renderCache->invalidateTags([CacheTag::forClass(TestEntity::class)]);

    self::assertSame('<p>2</p>', $renderCache->get('menu', 'main', $this->render(...)));
  }

  public function testInvalidateARenderOrAKindOfRender(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
    $renderCache->get('component', 12, $this->render(...));
    $renderCache->get('component', 13, $this->render(...));

    $renderCache->invalidate('component', 12);
    self::assertSame('<p>3</p>', $renderCache->get('component', 12, $this->render(...)));
    self::assertSame('<p>2</p>', $renderCache->get('component', 13, $this->render(...)));

    $renderCache->invalidate('component');
    self::assertSame('<p>4</p>', $renderCache->get('component', 13, $this->render(...)));
  }

  public function testTheAdditionalTagsAreUsed(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
    $renderCache->get('component', 12, $this->render(...), [], ['library:1']);

    $renderCache->invalidateTags(['library_1']);

    self::assertSame('<p>2</p>', $renderCache->get('component', 12, $this->render(...)));
  }

  public function testClearRemovesEveryRender(): void
  {
    $renderCache = new RenderCache(new TagAwareAdapter(new ArrayAdapter()), 0);
    $renderCache->get('component', 12, $this->render(...));

    self::assertTrue($renderCache->clear());

    self::assertSame('<p>2</p>', $renderCache->get('component', 12, $this->render(...)));
  }

  private function render(): string
  {
    ++$this->renders;

    return sprintf('<p>%d</p>', $this->renders);
  }
}
