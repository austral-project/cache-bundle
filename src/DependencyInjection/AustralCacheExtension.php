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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Austral Cache Extension.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class AustralCacheExtension extends Extension
{
  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function load(array $configs, ContainerBuilder $container): void
  {
    $configuration = new Configuration();
    $config = $this->processConfiguration($configuration, $configs);

    $defaultConfig = $configuration->getConfigDefault();
    $config = array_replace_recursive($defaultConfig, $config);
    $container->setParameter('austral_cache', $config);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    $loader->load('services.yaml');
  }

  /**
   * @return string
   */
  public function getNamespace(): string
  {
    return 'https://austral.dev/schema/dic/austral_cache';
  }
}
