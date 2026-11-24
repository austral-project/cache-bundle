<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatch EVENT_REBUILD to clear the caches and let the bundles rebuild theirs (admin, deployment…).
 * CacheRebuildListener clears the pools first (priority 1024); the bundles rebuild at the default priority.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class CacheRebuildEvent extends Event
{
  public const string EVENT_REBUILD = 'austral.cache.rebuild';

  /**
   * @param bool $result rebuild the results of Doctrine queries
   * @param bool $render rebuild the HTML renders
   */
  public function __construct(
    private readonly bool $result = true,
    private readonly bool $render = true
  ) {
  }

  /**
   * @return bool
   */
  public function isResult(): bool
  {
    return $this->result;
  }

  /**
   * @return bool
   */
  public function isRender(): bool
  {
    return $this->render;
  }
}
