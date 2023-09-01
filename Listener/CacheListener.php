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

use Austral\CacheBundle\Configuration\CacheConfiguration;
use Austral\ToolsBundle\Services\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Austral Cache Listener.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class CacheListener
{

  /**
   * @var CacheConfiguration
   */
  protected CacheConfiguration $configuration;

  /**
   * @var Debug
   */
  protected Debug $debug;

  /**
   * ControllerListener constructor.
   *
   * @param CacheConfiguration $configuration
   * @param Debug $debug
   */
  public function __construct(CacheConfiguration $configuration, Debug $debug)
  {
    $this->debug = $debug;
    $this->configuration = $configuration;
  }

  /**
   * @param ResponseEvent $responseEvent
   */
  public function onResponse(ResponseEvent $responseEvent)
  {
    if($this->cacheEnabled($responseEvent->getRequest()))
    {
      $responseEvent->getResponse()->setSharedMaxAge($this->configuration->get('shared_age_max'));
    }
  }

  protected function cacheEnabled(Request $request)
  {
    $currentDomain = $request->server->get('HTTP_HOST');
    $domainIsChecked = null;
    if($includeDomain = $this->configuration->get('include.domain'))
    {
      $domainIsChecked = false;
      if(in_array($currentDomain, $includeDomain))
      {
        $domainIsChecked = true;
      }
    }

    if(($excludeDomain = $this->configuration->get('exclude.domain')))
    {
      if(in_array($currentDomain, $excludeDomain))
      {
        $domainIsChecked = false;
      }
    }

    if($domainIsChecked === null)
    {
      $domainIsChecked = true;
    }

    $currentPath = $request->getRequestUri();
    $pathIsChecked = null;
    if($includePath = $this->configuration->get('include.path'))
    {
      $pathIsChecked = false;
      foreach ($includePath as $path)
      {
        if($this->checkPath($currentPath, $path))
        {
          $pathIsChecked = true;
          break;
        }
      }
    }
    if(($excludePath = $this->configuration->get('exclude.path')))
    {
      foreach ($excludePath as $path)
      {
        if($this->checkPath($currentPath, $path))
        {
          $pathIsChecked = false;
          break;
        }
      }
    }
    if($pathIsChecked === null)
    {
      $pathIsChecked = true;
    }
    return $pathIsChecked && $domainIsChecked;
  }

  /**
   * checkPath
   *
   * @param string $currentPath
   * @param string $path
   * @return bool
   */
  protected function checkPath(string $currentPath, string $path): bool
  {
    $pathIsChecked = false;
    if(str_starts_with($path, "^"))
    {
      $regex = str_replace("/", "\/", $path);
      $regex = str_replace("^", "", $regex);
      preg_match("/$regex/", $currentPath, $matches);
      if(count($matches) > 0)
      {
        $pathIsChecked = true;
      }
    }
    elseif($currentPath === $path)
    {
      $pathIsChecked = true;
    }
    return $pathIsChecked;
  }


}
