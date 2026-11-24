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

use Austral\CacheBundle\Cache\ResultCache;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ResultCacheTest extends TestCase
{
  public function testTheQueryResultIsCachedUnderTheKey(): void
  {
    $pool = new ArrayAdapter();
    $query = $this->createMock(AbstractQuery::class);
    $query->expects(self::once())->method('setResultCache')->with($pool)->willReturnSelf();
    $query->expects(self::once())->method('enableResultCache')->with(0, 'austral_domain.enabled_domains')->willReturnSelf();

    new ResultCache($pool, 0)->apply($query, 'austral_domain.enabled_domains');
  }

  public function testTheLifetimeAndTheKeyCanBeGiven(): void
  {
    $query = $this->createMock(AbstractQuery::class);
    $query->method('setResultCache')->willReturnSelf();
    $query->expects(self::once())->method('enableResultCache')->with(60, 'a_b')->willReturnSelf();

    new ResultCache(new ArrayAdapter(), 3600)->apply($query, 'a:b', 60);
  }

  public function testWithoutPoolNothingIsCached(): void
  {
    $query = $this->createMock(AbstractQuery::class);
    $query->expects(self::never())->method('setResultCache');
    $query->expects(self::never())->method('enableResultCache');

    $resultCache = new ResultCache(null, 0);
    $resultCache->apply($query, 'key');

    self::assertFalse($resultCache->isEnabled());
    self::assertTrue($resultCache->delete('key'));
    self::assertTrue($resultCache->deleteMultiple(['key']));
    self::assertTrue($resultCache->clear());
  }

  public function testTheKeysAreDeleted(): void
  {
    $pool = new ArrayAdapter();
    foreach (['one', 'two', 'three'] as $key)
    {
      $pool->save($pool->getItem($key)->set($key));
    }
    $resultCache = new ResultCache($pool, 0);

    self::assertTrue($resultCache->delete('one'));
    self::assertTrue($resultCache->deleteMultiple(['two']));
    self::assertTrue($resultCache->deleteMultiple([]));

    self::assertFalse($pool->hasItem('one'));
    self::assertFalse($pool->hasItem('two'));
    self::assertTrue($pool->hasItem('three'));

    self::assertTrue($resultCache->clear());
    self::assertFalse($pool->hasItem('three'));
  }
}
