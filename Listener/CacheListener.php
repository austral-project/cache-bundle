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

use Austral\CacheBundle\Services\HttpCacheEnabledChecker;
use Austral\ToolsBundle\Services\Debug;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Austral Cache Listener.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class CacheListener
{

  /**
   * @var HttpCacheEnabledChecker
   */
  protected HttpCacheEnabledChecker $cacheEnabledChecker;

  /**
   * @var Debug
   */
  protected Debug $debug;

  /**
   * ControllerListener constructor.
   *
   * @param HttpCacheEnabledChecker $cacheEnabledChecker
   * @param Debug $debug
   */
  public function __construct(HttpCacheEnabledChecker $cacheEnabledChecker, Debug $debug)
  {
    $this->debug = $debug;
    $this->cacheEnabledChecker = $cacheEnabledChecker;
  }

  /**
   * @param ResponseEvent $responseEvent
   */
  public function onResponse(ResponseEvent $responseEvent)
  {
    if($this->cacheEnabledChecker->checkByRequest($responseEvent->getRequest()))
    {
      $responseEvent->getResponse()->setSharedMaxAge($this->cacheEnabledChecker->getSharedMaxAge());
    }
  }
}
