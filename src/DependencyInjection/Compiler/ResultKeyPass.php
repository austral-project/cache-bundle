<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\DependencyInjection\Compiler;

use Austral\CacheBundle\Listener\EntityCacheListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Collects the result keys declared by the bundles, with the class whose changes invalidate them:
 *
 *   tags:
 *     - { name: austral.cache.result_key, key: 'austral_domain.enabled_domains', class: 'Austral\DomainBundle\Entity\Interfaces\DomainInterface' }
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class ResultKeyPass implements CompilerPassInterface
{
  public const string TAG = 'austral.cache.result_key';

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void
  {
    if (!$container->hasDefinition(EntityCacheListener::class))
    {
      return;
    }

    $resultKeys = [];
    foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags)
    {
      foreach ($tags as $tag)
      {
        $key = $tag['key'] ?? null;
        $class = $tag['class'] ?? null;
        if (!is_string($key) || '' === $key || !is_string($class) || '' === $class)
        {
          throw new InvalidArgumentException(sprintf('The tag "%s" of the service "%s" needs a "key" and a "class" attribute.', self::TAG, $id));
        }
        $resultKeys[$class][] = $key;
      }
    }
    foreach ($resultKeys as $class => $keys)
    {
      $resultKeys[$class] = array_values(array_unique($keys));
    }
    ksort($resultKeys);

    $container->getDefinition(EntityCacheListener::class)->setArgument('$resultKeys', $resultKeys);
  }
}
