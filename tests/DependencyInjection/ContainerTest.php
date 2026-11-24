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

namespace Austral\CacheBundle\Tests\DependencyInjection;

use Austral\CacheBundle\Configuration\CacheConfiguration;
use Austral\CacheBundle\DependencyInjection\AustralCacheExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The services are defined explicitly: a missing or misnamed argument only shows up when the container
 * is compiled or the service is created. This test loads the extension, compiles the container and
 * creates every service of the bundle.
 *
 * The 3.1 HTTP cache is removed: until the 4.0 rewrite, the bundle only defines its configuration.
 */
final class ContainerTest extends TestCase
{
  public function testTheConfigurationCanBeCreated(): void
  {
    self::assertInstanceOf(CacheConfiguration::class, self::compiledContainer()->get(CacheConfiguration::class));
  }

  public function testTheBundleDefinesNoOtherService(): void
  {
    $ids = [];
    foreach (self::loadExtension()->getDefinitions() as $id => $definition)
    {
      if (str_starts_with($id, 'Austral\\CacheBundle\\'))
      {
        $ids[] = $id;
      }
    }

    self::assertSame([CacheConfiguration::class], $ids);
  }

  /**
   * The configuration is the documented service: it is fetched by its public alias, its class id is private.
   */
  public function testOnlyTheConfigurationAliasIsPublic(): void
  {
    $container = self::loadExtension();

    self::assertFalse($container->getDefinition(CacheConfiguration::class)->isPublic());

    $aliases = [];
    foreach ($container->getAliases() as $id => $alias)
    {
      if (str_starts_with($id, 'austral.cache'))
      {
        $aliases[$id] = (string) $alias;
        self::assertTrue($alias->isPublic(), sprintf('The alias "%s" must be public.', $id));
      }
    }

    self::assertSame(['austral.cache.config' => CacheConfiguration::class], $aliases);
  }

  /**
   * Without the rewritten cache, nothing reads "enabled": it must stay off unless the application turns it on.
   */
  public function testTheCacheIsDisabledByDefault(): void
  {
    $configuration = self::compiledContainer()->get('austral.cache.config');
    self::assertInstanceOf(CacheConfiguration::class, $configuration);

    self::assertFalse($configuration->get('enabled'));
    self::assertSame(['enabled' => false], self::loadExtension()->getParameter('austral_cache'));
  }

  public function testTheCacheCanBeEnabled(): void
  {
    $configuration = self::compiledContainer([['enabled' => true]])->get('austral.cache.config');
    self::assertInstanceOf(CacheConfiguration::class, $configuration);

    self::assertTrue($configuration->get('enabled'));
  }

  /**
   * The 3.1 options (HTTP cache) are removed: an application that still sets one gets an explicit error.
   */
  public function testTheOptionsOf31AreRejected(): void
  {
    $this->expectExceptionMessageMatches('/clearAuto/');

    self::loadExtension([['enabled' => false, 'clearAuto' => true]]);
  }

  public function testTheXmlNamespace(): void
  {
    self::assertSame('https://austral.dev/schema/dic/austral_cache', new AustralCacheExtension()->getNamespace());
  }

  /**
   * @param list<array<string, mixed>> $configs
   */
  private static function loadExtension(array $configs = []): ContainerBuilder
  {
    $container = new ContainerBuilder();
    new AustralCacheExtension()->load($configs, $container);

    return $container;
  }

  /**
   * @param list<array<string, mixed>> $configs
   */
  private static function compiledContainer(array $configs = []): ContainerBuilder
  {
    $container = self::loadExtension($configs);
    $container->getDefinition(CacheConfiguration::class)->setPublic(true);
    $container->compile();

    return $container;
  }
}
