<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\DependencyInjection;

use Austral\CacheBundle\Cache\CacheContextProviderInterface;
use Austral\CacheBundle\Cache\RenderCache;
use Austral\CacheBundle\Cache\ResultCache;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Austral Cache Extension.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class AustralCacheExtension extends Extension implements PrependExtensionInterface
{
  /**
   * Pool of the results of Doctrine queries.
   */
  public const string RESULT_POOL = 'austral_cache.result';

  /**
   * Pool of the HTML renders (tag aware).
   */
  public const string RENDER_POOL = 'austral_cache.render';

  /**
   * Declares the two pools in FrameworkBundle (framework.cache.pools), so that they are created, cleared
   * (cache:pool:clear austral_cache.render) and profiled like the pools of the application.
   *
   * @param ContainerBuilder $container
   *
   * @return void
   */
  public function prepend(ContainerBuilder $container): void
  {
    if (!$container->hasExtension('framework'))
    {
      return;
    }
    $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
    if (!$config['enabled'] || Configuration::ADAPTER_POOL === $config['adapter'])
    {
      return;
    }

    $pool = ['default_lifetime' => $config['ttl']];
    if (Configuration::ADAPTER_REDIS === $config['adapter'])
    {
      $pool['adapter'] = 'cache.adapter.redis';
      $pool['provider'] = $config['dsn'];
    }
    else
    {
      $pool['adapter'] = 'cache.adapter.filesystem';
    }
    $container->prependExtensionConfig('framework', ['cache' => ['pools' => [
      self::RESULT_POOL => $pool,
      self::RENDER_POOL => $pool + ['tags' => true],
    ]]]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function load(array $configs, ContainerBuilder $container): void
  {
    $config = $this->processConfiguration(new Configuration(), $configs);
    $container->setParameter('austral_cache', $config);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    $loader->load('services.yaml');

    // A context provider of the application is tagged automatically (rule 6 of the convention).
    $container->registerForAutoconfiguration(CacheContextProviderInterface::class)->addTag('austral.cache.context_provider');

    $resultPool = null;
    $renderPool = null;
    if ($config['enabled'])
    {
      if (Configuration::ADAPTER_POOL === $config['adapter'])
      {
        // A pool of the application: the renders are tagged through a TagAwareAdapter on top of it.
        $resultPool = new Reference($config['pool']);
        $container->setDefinition(self::RENDER_POOL, new Definition(TagAwareAdapter::class, [new Reference($config['pool'])]));
        $renderPool = new Reference(self::RENDER_POOL);
      }
      else
      {
        // Declared in FrameworkBundle by prepend(); without FrameworkBundle the references are null: no cache.
        $resultPool = new Reference(self::RESULT_POOL, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $renderPool = new Reference(self::RENDER_POOL, ContainerInterface::NULL_ON_INVALID_REFERENCE);
      }
    }
    $container->getDefinition(ResultCache::class)->setArgument('$pool', $resultPool)->setArgument('$ttl', $config['ttl']);
    $container->getDefinition(RenderCache::class)->setArgument('$pool', $renderPool)->setArgument('$ttl', $config['ttl']);
  }

  /**
   * @return string
   */
  public function getNamespace(): string
  {
    return 'https://austral.dev/schema/dic/austral_cache';
  }
}
