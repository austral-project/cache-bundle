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

/**
 * Part of the key of the renders: a bundle that changes the HTML of a render (the current domain,
 * the language…) provides it, so that each context has its own render (tag austral.cache.context_provider).
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
interface CacheContextProviderInterface
{
  /**
   * @return array<string, string|int|bool|null> for example ['domain' => 'abcd-1234']
   */
  public function getCacheContext(): array;
}
