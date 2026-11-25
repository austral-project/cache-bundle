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

use Austral\CacheBundle\Cache\LocaleCacheContext;
use Austral\CacheBundle\Cache\RenderCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LocaleCacheContextTest extends TestCase
{
  public function testTheLocaleOfTheCurrentRequestIsPartOfTheContext(): void
  {
    $requestStack = new RequestStack();
    $context = new LocaleCacheContext($requestStack);

    $requestStack->push(self::request('fr'));
    self::assertSame(['locale' => 'fr'], $context->getCacheContext());

    // A sub-request can have its own locale
    $requestStack->push(self::request('en'));
    self::assertSame(['locale' => 'en'], $context->getCacheContext());
  }

  public function testThereIsNoLocaleWithoutRequest(): void
  {
    self::assertSame(['locale' => null], new LocaleCacheContext(new RequestStack())->getCacheContext());
  }

  public function testEachLocaleHasItsOwnKey(): void
  {
    $requestStack = new RequestStack();
    $renderCache = new RenderCache(null, 0, [new LocaleCacheContext($requestStack)]);

    $requestStack->push(self::request('fr'));
    $frenchKey = $renderCache->key('component', 12);
    self::assertSame($frenchKey, $renderCache->key('component', 12));

    $requestStack->pop();
    $requestStack->push(self::request('en'));
    self::assertNotSame($frenchKey, $renderCache->key('component', 12));
  }

  private static function request(string $locale): Request
  {
    $request = Request::create('/');
    $request->setLocale($locale);

    return $request;
  }
}
