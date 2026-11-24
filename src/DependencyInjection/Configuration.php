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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Austral Cache Configuration.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class Configuration implements ConfigurationInterface
{
  public const string ADAPTER_FILESYSTEM = 'filesystem';
  public const string ADAPTER_REDIS = 'redis';
  public const string ADAPTER_POOL = 'pool';

  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder(): TreeBuilder
  {
    $treeBuilder = new TreeBuilder('austral_cache');

    $treeBuilder->getRootNode()
      ->children()
        ->booleanNode('enabled')->defaultTrue()->end()
        ->enumNode('adapter')
          ->info('filesystem (one cache per server), redis (shared between servers) or pool (a cache pool of the application).')
          ->values([self::ADAPTER_FILESYSTEM, self::ADAPTER_REDIS, self::ADAPTER_POOL])
          ->defaultValue(self::ADAPTER_FILESYSTEM)
        ->end()
        ->scalarNode('dsn')
          ->info('Redis DSN, for the redis adapter (e.g. "%env(REDIS_URL)%").')
          ->defaultNull()
        ->end()
        ->scalarNode('pool')
          ->info('Service id of a cache pool of the application, for the pool adapter.')
          ->defaultNull()
        ->end()
        ->integerNode('ttl')
          ->info('Lifetime in seconds, 0 for no expiration: the cache is invalidated when an entity is saved.')
          ->min(0)
          ->defaultValue(0)
        ->end()
      ->end()
      ->validate()
        ->ifTrue(static fn (array $config): bool => $config['enabled'] && self::ADAPTER_REDIS === $config['adapter'] && empty($config['dsn']))
        ->thenInvalid('austral_cache.dsn is required with the "redis" adapter.')
      ->end()
      ->validate()
        ->ifTrue(static fn (array $config): bool => $config['enabled'] && self::ADAPTER_POOL === $config['adapter'] && empty($config['pool']))
        ->thenInvalid('austral_cache.pool is required with the "pool" adapter.')
      ->end();

    return $treeBuilder;
  }
}
