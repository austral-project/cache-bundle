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
 * @author Matthieu Beurel <matthieu@austral.dev>
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
    $node->integerNode("shared_age_max")->defaultValue(3600)->end();
    $node->booleanNode("enabled")->defaultValue(true)->end();
    $node->booleanNode("clearAuto")->defaultValue(false)->end();
    $node->arrayNode("include")
      ->children()
        ->arrayNode("path")
          ->scalarPrototype()->end()
        ->end()
        ->arrayNode("domain")
          ->scalarPrototype()->end()
        ->end()
      ->end()
    ->end();

    $node->arrayNode("exclude")
      ->children()
        ->arrayNode("path")
          ->scalarPrototype()->end()
        ->end()
        ->arrayNode("domain")
          ->scalarPrototype()->end()
        ->end()
      ->end()
    ->end();



    return $treeBuilder;
  }


  /**
   * @return array
   */
  public function getConfigDefault(): array
  {
    return array(
      "enabled"           =>  false,
      "clearAuto"         =>  false,
      "shared_age_max"    =>  3600,
      "include"           =>  array(
        "path"              =>  array(),
        "domain"            =>  array()
      ),
      "exclude"           =>  array(
        "path"              =>  array(),
        "domain"            =>  array()
      ),
    );
  }

}
