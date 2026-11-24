<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Listener;

use Austral\CacheBundle\Cache\RenderCache;
use Austral\CacheBundle\Cache\ResultCache;
use Austral\CacheBundle\Event\CacheRebuildEvent;

/**
 * Clears the pools when a rebuild is asked (priority 1024), before the bundles rebuild their caches.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class CacheRebuildListener
{
  /**
   * @param RenderCache $renderCache
   * @param ResultCache $resultCache
   */
  public function __construct(protected RenderCache $renderCache, protected ResultCache $resultCache)
  {
  }

  /**
   * @param CacheRebuildEvent $event
   *
   * @return void
   */
  public function onRebuild(CacheRebuildEvent $event): void
  {
    if ($event->isResult())
    {
      $this->resultCache->clear();
    }
    if ($event->isRender())
    {
      $this->renderCache->clear();
    }
  }
}
