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

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds the locale of the current request to the key of the renders: each language has its own render.
 * In Austral, http-bundle sets the locale of the request from the language of the request (HttpListener).
 * Without request (console), the locale is null.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class LocaleCacheContext implements CacheContextProviderInterface
{
  public function __construct(protected RequestStack $requestStack)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContext(): array
  {
    return ['locale' => $this->requestStack->getCurrentRequest()?->getLocale()];
  }
}
