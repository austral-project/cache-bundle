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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Austral HttpCache Event.
 * @author Matthieu Beurel <matthieu@austral.dev>
 * @final
 */
class HttpCacheEvent extends Event
{

  const EVENT_CLEAR_HTTP_CACHE = "austral.cache.clear_http_cache";

  /**
   * @var Request|null
   */
  protected ?Request $request = null;

  /**
   * HttpCacheEvent constructor
   *
   * @param string|null $uri
   */
  public function __construct(?string $uri = null)
  {
    if($uri)
    {
      $this->request = Request::create($uri);
    }
  }

  /**
   * getRequest
   *
   * @return Request|null
   */
  public function getRequest(): ?Request
  {
    return $this->request;
  }

  /**
   * setRequest
   *
   * @param Request|null $request
   * @return $this
   */
  public function setRequest(?Request $request): HttpCacheEvent
  {
    $this->request = $request;
    return $this;
  }

}