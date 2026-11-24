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

use Austral\CacheBundle\AustralCacheBundle;
use Austral\CacheBundle\Cache\CacheContextProviderInterface;
use Austral\CacheBundle\Cache\RenderCache;
use Austral\CacheBundle\Cache\ResultCache;
use Austral\CacheBundle\Command\CacheRebuildCommand;
use Austral\CacheBundle\Configuration\CacheConfiguration;
use Austral\CacheBundle\DependencyInjection\AustralCacheExtension;
use Austral\CacheBundle\Listener\CacheRebuildListener;
use Austral\CacheBundle\Listener\EntityCacheListener;
use Austral\CacheBundle\Tests\Fixtures\TestContextProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The services are defined explicitly: a missing or misnamed argument only shows up when the container
 * is compiled or the service is created. This test loads the extension, compiles the container and
 * creates every service of the bundle; it also checks the pools declared in FrameworkBundle.
 */
final class ContainerTest extends TestCase
{
  /**
   * Public aliases: the supported way to get a service from the container.
   */
  private const array PUBLIC_ALIASES = [
    'austral.cache.config' => CacheConfiguration::class,
    'austral.cache.render' => RenderCache::class,
    'austral.cache.result' => ResultCache::class,
  ];

  /**
   * @return iterable<string, array{class-string}>
   */
  public static function serviceIds(): iterable
  {
    foreach (self::bundleServices() as $class)
    {
      yield $class => [$class];
    }
  }

  /**
   * @param class-string $class
   */
  #[DataProvider('serviceIds')]
  public function testEveryServiceCanBeCreated(string $class): void
  {
    self::assertInstanceOf($class, self::compiledContainer()->get($class));
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
    sort($ids);

    $expected = self::bundleServices();
    sort($expected);

    self::assertSame($expected, $ids);
  }

  public function testOnlyTheDocumentedAliasesArePublic(): void
  {
    $container = self::loadExtension();

    foreach ($container->getDefinitions() as $id => $definition)
    {
      if (str_starts_with($id, 'Austral\\CacheBundle\\'))
      {
        self::assertFalse($definition->isPublic(), sprintf('The service "%s" must be private.', $id));
      }
    }

    $aliases = [];
    foreach ($container->getAliases() as $id => $alias)
    {
      if (str_starts_with($id, 'austral.cache'))
      {
        self::assertTrue($alias->isPublic(), sprintf('The alias "%s" must be public.', $id));
        $aliases[$id] = (string) $alias;
      }
    }
    ksort($aliases);

    self::assertSame(self::PUBLIC_ALIASES, $aliases);
  }

  public function testTheConfigurationHasItsDefaults(): void
  {
    self::assertSame(
      ['enabled' => true, 'adapter' => 'filesystem', 'dsn' => null, 'pool' => null, 'ttl' => 0],
      self::loadExtension()->getParameter('austral_cache')
    );
  }

  public function testThePoolsOfFrameworkBundleAreReferenced(): void
  {
    $container = self::loadExtension();

    self::assertEquals(new Reference(AustralCacheExtension::RESULT_POOL, ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(ResultCache::class)->getArgument('$pool'));
    self::assertEquals(new Reference(AustralCacheExtension::RENDER_POOL, ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(RenderCache::class)->getArgument('$pool'));
    self::assertSame(0, $container->getDefinition(ResultCache::class)->getArgument('$ttl'));
  }

  /**
   * Without FrameworkBundle, the pools do not exist: the caches are disabled, the services still work.
   */
  public function testWithoutFrameworkBundleThereIsNoCache(): void
  {
    $container = self::compiledContainer();

    self::assertFalse(self::resultCache($container)->isEnabled());
    self::assertFalse(self::renderCache($container)->isEnabled());
  }

  public function testADisabledCacheHasNoPool(): void
  {
    $container = self::loadExtension([['enabled' => false]]);

    self::assertNull($container->getDefinition(ResultCache::class)->getArgument('$pool'));
    self::assertNull($container->getDefinition(RenderCache::class)->getArgument('$pool'));
  }

  public function testThePoolOfTheApplicationIsUsed(): void
  {
    $container = self::loadExtension([['adapter' => 'pool', 'pool' => 'app.cache', 'ttl' => 60]]);

    self::assertEquals(new Reference('app.cache'), $container->getDefinition(ResultCache::class)->getArgument('$pool'));
    self::assertSame(60, $container->getDefinition(ResultCache::class)->getArgument('$ttl'));
    $renderPool = $container->getDefinition(AustralCacheExtension::RENDER_POOL);
    self::assertSame(TagAwareAdapter::class, $renderPool->getClass());
    self::assertEquals([new Reference('app.cache')], $renderPool->getArguments());

    $container->setDefinition('app.cache', new Definition(ArrayAdapter::class));
    self::compile($container);
    self::assertTrue(self::resultCache($container)->isEnabled());
    self::assertTrue(self::renderCache($container)->isEnabled());
  }

  /**
   * @return iterable<string, array{array<string, mixed>, string}>
   */
  public static function invalidConfigurations(): iterable
  {
    yield 'redis without dsn' => [['adapter' => 'redis'], 'austral_cache.dsn is required'];
    yield 'pool without pool' => [['adapter' => 'pool'], 'austral_cache.pool is required'];
    yield 'unknown adapter' => [['adapter' => 'memcached'], 'memcached'];
    yield 'negative ttl' => [['ttl' => -1], 'ttl'];
  }

  /**
   * @param array<string, mixed> $config
   */
  #[DataProvider('invalidConfigurations')]
  public function testAnInvalidConfigurationIsRejected(array $config, string $message): void
  {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($message);

    self::loadExtension([$config]);
  }

  public function testPrependDeclaresTheFilesystemPoolsInFrameworkBundle(): void
  {
    self::assertSame([
      AustralCacheExtension::RESULT_POOL => ['default_lifetime' => 0, 'adapter' => 'cache.adapter.filesystem'],
      AustralCacheExtension::RENDER_POOL => ['default_lifetime' => 0, 'adapter' => 'cache.adapter.filesystem', 'tags' => true],
    ], self::prependedPools([]));
  }

  public function testPrependDeclaresTheRedisPoolsInFrameworkBundle(): void
  {
    self::assertSame([
      AustralCacheExtension::RESULT_POOL => ['default_lifetime' => 3600, 'adapter' => 'cache.adapter.redis', 'provider' => 'redis://localhost'],
      AustralCacheExtension::RENDER_POOL => ['default_lifetime' => 3600, 'adapter' => 'cache.adapter.redis', 'provider' => 'redis://localhost', 'tags' => true],
    ], self::prependedPools(['adapter' => 'redis', 'dsn' => 'redis://localhost', 'ttl' => 3600]));
  }

  public function testPrependDeclaresNoPoolWhenDisabledOrWithThePoolOfTheApplication(): void
  {
    self::assertNull(self::prependedPools(['enabled' => false]));
    self::assertNull(self::prependedPools(['adapter' => 'pool', 'pool' => 'app.cache']));
  }

  public function testTheEntityCacheListenerListensToTheFlush(): void
  {
    $definition = self::loadExtension()->getDefinition(EntityCacheListener::class);

    self::assertSame([['event' => 'onFlush'], ['event' => 'postFlush']], $definition->getTag('doctrine.event_listener'));
    self::assertSame([['method' => 'reset']], $definition->getTag('kernel.reset'));
  }

  public function testThePoolsAreClearedBeforeTheBundlesRebuild(): void
  {
    $tags = self::loadExtension()->getDefinition(CacheRebuildListener::class)->getTag('kernel.event_listener');

    self::assertSame([['event' => 'austral.cache.rebuild', 'method' => 'onRebuild', 'priority' => 1024]], $tags);
  }

  public function testTheRenderCacheIsResetBetweenTwoRequests(): void
  {
    self::assertSame([['method' => 'reset']], self::loadExtension()->getDefinition(RenderCache::class)->getTag('kernel.reset'));
  }

  public function testTheCommandIsRegisteredWithItsNameAndTheEventDispatcher(): void
  {
    $container = self::compiledContainer();

    $tags = $container->findTaggedServiceIds('console.command');
    self::assertSame('austral:cache:rebuild', $tags[CacheRebuildCommand::class][0]['command'] ?? null);

    $command = $container->get(CacheRebuildCommand::class);
    self::assertInstanceOf(CacheRebuildCommand::class, $command);
    self::assertInstanceOf(EventDispatcherInterface::class, new \ReflectionProperty($command, 'eventDispatcher')->getValue($command));
  }

  public function testTheResultKeysDeclaredByTheBundlesAreCollected(): void
  {
    $container = self::loadExtension();
    $container->setDefinition('app.domains', new Definition(\stdClass::class))
      ->addTag('austral.cache.result_key', ['key' => 'austral_domain.enabled_domains', 'class' => 'App\\Domain'])
      ->addTag('austral.cache.result_key', ['key' => 'app.menus', 'class' => 'App\\Domain'])
      ->addTag('austral.cache.result_key', ['key' => 'app.pages', 'class' => 'App\\Page']);
    self::compile($container);

    $listener = $container->get(EntityCacheListener::class);
    self::assertInstanceOf(EntityCacheListener::class, $listener);
    self::assertSame([
      'App\\Domain' => ['austral_domain.enabled_domains', 'app.menus'],
      'App\\Page' => ['app.pages'],
    ], new \ReflectionProperty($listener, 'resultKeys')->getValue($listener));
  }

  public function testAResultKeyWithoutClassIsRejected(): void
  {
    $container = self::loadExtension();
    $container->setDefinition('app.domains', new Definition(\stdClass::class))
      ->addTag('austral.cache.result_key', ['key' => 'austral_domain.enabled_domains']);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('needs a "key" and a "class" attribute');

    self::compile($container);
  }

  public function testAContextProviderOfTheApplicationIsTagged(): void
  {
    $instanceof = self::loadExtension()->getAutoconfiguredInstanceof();

    self::assertArrayHasKey(CacheContextProviderInterface::class, $instanceof);
    self::assertTrue($instanceof[CacheContextProviderInterface::class]->hasTag('austral.cache.context_provider'));
  }

  public function testTheContextProvidersAreGivenToTheRenderCache(): void
  {
    $container = self::loadExtension();
    $container->setDefinition(TestContextProvider::class, new Definition(TestContextProvider::class, [['domain' => 'a']]))
      ->addTag('austral.cache.context_provider');
    self::compile($container);

    self::assertNotSame('austral.render.component.12', self::renderCache($container)->key('component', 12));
  }

  public function testTheXmlNamespace(): void
  {
    self::assertSame('https://austral.dev/schema/dic/austral_cache', new AustralCacheExtension()->getNamespace());
  }

  /**
   * @return list<class-string>
   */
  private static function bundleServices(): array
  {
    return [
      CacheConfiguration::class,
      ResultCache::class,
      RenderCache::class,
      EntityCacheListener::class,
      CacheRebuildListener::class,
      CacheRebuildCommand::class,
    ];
  }

  /**
   * @param array<string, mixed> $config
   *
   * @return array<string, mixed>|null pools prepended in the configuration of FrameworkBundle
   */
  private static function prependedPools(array $config): ?array
  {
    $container = new ContainerBuilder();
    $container->registerExtension(new FrameworkExtension());
    $extension = new AustralCacheExtension();
    $container->registerExtension($extension);
    $container->loadFromExtension('austral_cache', $config);

    $extension->prepend($container);

    $frameworkConfigs = $container->getExtensionConfig('framework');

    return $frameworkConfigs[0]['cache']['pools'] ?? null;
  }

  /**
   * The services that the bundle needs from the application (the event dispatcher) are added as minimal definitions.
   *
   * @param list<array<string, mixed>> $configs
   */
  private static function loadExtension(array $configs = []): ContainerBuilder
  {
    $container = new ContainerBuilder();
    $container->setDefinition('event_dispatcher', new Definition(EventDispatcher::class));
    new AustralCacheBundle()->build($container);
    new AustralCacheExtension()->load($configs, $container);

    return $container;
  }

  /**
   * @param list<array<string, mixed>> $configs
   */
  private static function compiledContainer(array $configs = []): ContainerBuilder
  {
    $container = self::loadExtension($configs);
    self::compile($container);

    return $container;
  }

  private static function compile(ContainerBuilder $container): void
  {
    foreach ($container->getDefinitions() as $id => $definition)
    {
      if (str_starts_with($id, 'Austral\\CacheBundle\\'))
      {
        $definition->setPublic(true);
      }
    }
    $container->compile();
  }

  private static function resultCache(ContainerBuilder $container): ResultCache
  {
    $resultCache = $container->get(ResultCache::class);
    self::assertInstanceOf(ResultCache::class, $resultCache);

    return $resultCache;
  }

  private static function renderCache(ContainerBuilder $container): RenderCache
  {
    $renderCache = $container->get(RenderCache::class);
    self::assertInstanceOf(RenderCache::class, $renderCache);

    return $renderCache;
  }
}
