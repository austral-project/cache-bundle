<?php
/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Austral\CacheBundle\EventSubscriber;

use Austral\CacheBundle\Configuration\CacheConfiguration;
use Austral\CacheBundle\Event\HttpCacheEvent;
use Austral\CacheBundle\Services\HttpCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Austral HttpCache Subscriber.
 * @author Matthieu Beurel <matthieu@austral.dev>
 * @final
 */
class HttpCacheSubscriber implements EventSubscriberInterface
{

  /**
   * @var HttpCache
   */
  protected HttpCache $httpCache;

  /**
   * @var CacheConfiguration
   */
  protected CacheConfiguration $cacheConfiguration;

  /**
   * @return array
   */
  public static function getSubscribedEvents()
  {
    return [
      HttpCacheEvent::EVENT_CLEAR_HTTP_CACHE         =>  ["clearCache", 1024],
    ];
  }

  /**
   * HttpCacheSubscriber constructor
   *
   * @param HttpCache $httpCache
   * @param CacheConfiguration $cacheConfiguration
   */
  public function __construct(HttpCache $httpCache, CacheConfiguration $cacheConfiguration)
  {
    $this->httpCache = $httpCache;
    $this->cacheConfiguration = $cacheConfiguration;
  }

  /**
   * @param HttpCacheEvent $httpCacheEvent
   */
  public function clearCache(HttpCacheEvent $httpCacheEvent)
  {
    if($this->cacheConfiguration->get("enabled"))
    {
      if($httpCacheEvent->getRequest())
      {
        $this->httpCache->purge($httpCacheEvent->getRequest());
      }
      else
      {
        $this->httpCache->purgeAll();
      }
    }
  }

}