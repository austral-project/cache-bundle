<?php
/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Austral\CacheBundle\Configuration;

use Austral\ToolsBundle\Configuration\BaseConfiguration;

/**
 * Austral Cache Configuration.
 * @author Matthieu Beurel <matthieu@austral.dev>
 * @final
 */
Class CacheConfiguration extends BaseConfiguration
{

  /**
   * @var string|null
   */
  protected ?string $prefix = "cache";

  /**
   * @var int|null
   */
  protected ?int $niveauMax = null;

}