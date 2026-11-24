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

namespace Austral\CacheBundle\Tests\Fixtures;

use Doctrine\Persistence\Proxy;

/**
 * Doctrine proxy of TestEntity: the tags must use the class of the entity, not the class of the proxy.
 *
 * @implements Proxy<TestEntity>
 */
class TestEntityProxy extends TestEntity implements Proxy
{
  public function __load(): void
  {
  }

  public function __isInitialized(): bool
  {
    return true;
  }
}
