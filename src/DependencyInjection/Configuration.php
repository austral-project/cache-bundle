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
  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder(): TreeBuilder
  {
    $treeBuilder = new TreeBuilder('austral_cache');
    $rootNode = $treeBuilder->getRootNode();
    $node = $rootNode->children();
    $node->booleanNode('enabled')->defaultFalse()->end();

    return $treeBuilder;
  }

  /**
   * @return array<string, mixed>
   */
  public function getConfigDefault(): array
  {
    return [
      'enabled' => false,
    ];
  }
}
