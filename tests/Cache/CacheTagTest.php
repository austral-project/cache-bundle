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
use Austral\CacheBundle\Tests\Fixtures\TestEntity;
use Austral\CacheBundle\Tests\Fixtures\TestEntityProxy;
use PHPUnit\Framework\TestCase;

final class CacheTagTest extends TestCase
{
  public function testTheTagOfAClassUsesDotsInsteadOfBackslashes(): void
  {
    /** @var class-string $classWithLeadingBackslash */
    $classWithLeadingBackslash = '\\'.TestEntity::class;

    self::assertSame('austral.entity.Austral.CacheBundle.Tests.Fixtures.TestEntity', CacheTag::forClass(TestEntity::class));
    self::assertSame('austral.entity.Austral.CacheBundle.Tests.Fixtures.TestEntity', CacheTag::forClass($classWithLeadingBackslash));
  }

  public function testTheReservedCharactersOfPsr6AreReplaced(): void
  {
    self::assertSame('austral.entity.stdClass.a_b_c_d_e_f_g_h', CacheTag::forClassAndId(\stdClass::class, 'a{b}c(d)e/f@g:h'));
    self::assertSame('a_b', CacheTag::sanitize('a\\b'));
  }

  public function testTheTagsOfAnEntityAreItsClassAndItsId(): void
  {
    self::assertSame([
      'austral.entity.Austral.CacheBundle.Tests.Fixtures.TestEntity',
      'austral.entity.Austral.CacheBundle.Tests.Fixtures.TestEntity.42',
    ], CacheTag::forEntity(new TestEntity('42')));
  }

  public function testAnEntityWithoutIdOnlyHasTheTagOfItsClass(): void
  {
    self::assertSame(['austral.entity.Austral.CacheBundle.Tests.Fixtures.TestEntity'], CacheTag::forEntity(new TestEntity()));
    self::assertSame(['austral.entity.stdClass'], CacheTag::forEntity(new \stdClass()));
  }

  public function testTheMostPreciseTagOfAnEntity(): void
  {
    self::assertSame('austral.entity.Austral.CacheBundle.Tests.Fixtures.TestEntity.42', CacheTag::ofEntity(new TestEntity('42')));
    self::assertSame('austral.entity.Austral.CacheBundle.Tests.Fixtures.TestEntity', CacheTag::ofEntity(new TestEntity()));
  }

  public function testTheTagsOfAProxyUseTheClassOfTheEntity(): void
  {
    self::assertSame(TestEntity::class, CacheTag::realClass(new TestEntityProxy('7')));
    self::assertSame(CacheTag::forEntity(new TestEntity('7')), CacheTag::forEntity(new TestEntityProxy('7')));
  }

  public function testTheTagsOfARender(): void
  {
    self::assertSame('austral.render.component', CacheTag::forRender('component'));
    self::assertSame('austral.render.component.12', CacheTag::forRender('component', 12));
  }
}
