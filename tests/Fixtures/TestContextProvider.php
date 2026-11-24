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

use Austral\CacheBundle\Cache\CacheContextProviderInterface;

final class TestContextProvider implements CacheContextProviderInterface
{
  /**
   * @param array<string, string|int|bool|null> $context
   */
  public function __construct(public array $context = [])
  {
  }

  public function getCacheContext(): array
  {
    return $this->context;
  }
}
