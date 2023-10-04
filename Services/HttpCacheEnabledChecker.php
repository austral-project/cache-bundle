<?php
/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Services;

use Austral\CacheBundle\Configuration\CacheConfiguration;
use Austral\ToolsBundle\Services\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class HttpCacheEnabledChecker
 *
 * @package Austral\CacheBundle\Services
 *
 * @author  Matthieu Beurel <matthieu@austral.dev>
 * @final
 */
class HttpCacheEnabledChecker
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
   * @var bool|null
   */
  protected ?bool $enabledByUrl = true;

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
   * getSharedMaxAge
   *
   * @return string
   */
  public function getSharedMaxAge(): string
  {
    return (string) $this->configuration->get('shared_age_max');
  }

  /**
   * setEnabledByUrl
   *
   * @param bool|null $enabledByUrl
   * @param bool $force
   * @return $this
   */
  public function setEnabledByUrl(?bool $enabledByUrl, bool $force = false): HttpCacheEnabledChecker
  {
    if($force || $this->enabledByUrl)
    {
      $this->enabledByUrl = $enabledByUrl;
    }
    return $this;
  }

  /**
   * enabledCache
   *
   * @param Request|null $request
   * @return bool
   */
  public function checkByRequest(?Request $request = null): bool
  {
    $enabledCache = false;
    if($request)
    {
      $enabledCache = $this->checkByDomainAndUri($request->server->get('HTTP_HOST'), $request->getRequestUri());
    }
    return $enabledCache;
  }

  /**
   * cacheEnabled
   *
   * @param string|null $domain
   * @param string|null $uri
   * @return bool
   */
  public function checkByDomainAndUri(?string $domain = null, ?string $uri = null): bool
  {
    return $this->checkByDomain($domain) && $this->checkByUri($uri);
  }

  /**
   * checkByDomain
   *
   * @param string|null $domain
   * @return bool|null
   */
  public function checkByDomain(?string $domain = null): ?bool
  {
    $domainIsChecked = null;
    if($domain && $this->configuration->get('enabled'))
    {
      if($includeDomain = $this->configuration->get('include.domain'))
      {
        $domainIsChecked = false;
        if(in_array($domain, $includeDomain))
        {
          $domainIsChecked = true;
        }
      }

      if(($excludeDomain = $this->configuration->get('exclude.domain')))
      {
        if(in_array($domain, $excludeDomain))
        {
          $domainIsChecked = false;
        }
      }
    }
    if($domainIsChecked === null)
    {
      $domainIsChecked = $this->configuration->get('enabled');
    }
    return $domainIsChecked;
  }

  /**
   * checkByUri
   *
   * @param string|null $uri
   * @return bool|null
   */
  public function checkByUri(?string $uri = null): ?bool
  {
    $pathIsChecked = null;
    if($uri && $this->configuration->get('enabled'))
    {
      if($includePath = $this->configuration->get('include.path'))
      {
        $pathIsChecked = false;
        foreach ($includePath as $path)
        {
          if($this->checkPath($uri, $path))
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
          if($this->checkPath($uri, $path))
          {
            $pathIsChecked = false;
            break;
          }
        }
      }
    }
    if($pathIsChecked === null)
    {
      $pathIsChecked = $this->configuration->get('enabled');
    }
    return $this->enabledByUrl && $pathIsChecked;
  }

  /**
   * checkPath
   *
   * @param string $uri
   * @param string $path
   * @return bool
   */
  protected function checkPath(string $uri, string $path): bool
  {
    $pathIsChecked = false;
    if(str_starts_with($path, "^"))
    {
      $regex = str_replace("/", "\/", $path);
      $regex = str_replace("^", "", $regex);
      preg_match("/$regex/", $uri, $matches);
      if(count($matches) > 0)
      {
        $pathIsChecked = true;
      }
    }
    elseif($uri === $path)
    {
      $pathIsChecked = true;
    }
    return $pathIsChecked;
  }

}