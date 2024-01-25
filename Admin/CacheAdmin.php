<?php
/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\CacheBundle\Admin;

use Austral\AdminBundle\Admin\Admin;
use Austral\AdminBundle\Admin\Event\ListAdminEvent;
use Austral\ListBundle\Column\Action;
use Symfony\Component\HttpFoundation\Request;

/**
 * Austral Cache Admin.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class CacheAdmin extends Admin
{

  /**
   * @param ListAdminEvent $listAdminEvent
   *
   */
  public function index(ListAdminEvent $listAdminEvent)
  {
    $urlsByCache = array();
    $urlsInCache = 0;
    $urlsTotal = 0;
    $urlsDisabled = 0;
    $httpCache = $this->container->get("http_cache");
    $httpCacheEnabledChecker = $this->container->get("austral.cache.http_cache_enabled_checker");

    if($this->container->has('austral.seo.url_parameter.management'))
    {
      $australRouting = $this->container->get('austral.seo.routing');
      $urlParametersManagement = $this->container->get('austral.seo.url_parameter.management');
      foreach ($urlParametersManagement->getUrlParametersByDomains() as $urlParametersByDomain)
      {
        /**
         * @var string $langue
         * @var \Austral\SeoBundle\Model\UrlParametersByDomain $urlParametersByDomainAndLanguage
         */
        foreach ($urlParametersByDomain as $langue => $urlParametersByDomainAndLanguage)
        {
          if(!$urlParametersByDomainAndLanguage->getDomain()->getLanguage() || ($langue === $urlParametersByDomainAndLanguage->getDomain()->getLanguage()))
          {
            if(!array_key_exists($urlParametersByDomainAndLanguage->getDomain()->getDomain(), $urlsByCache))
            {
              $urlsByCache[$urlParametersByDomainAndLanguage->getDomain()->getDomain()] = array();
            }
            $urlsByCache[$urlParametersByDomainAndLanguage->getDomain()->getDomain()][$langue] = array();

            /** @var \Austral\SeoBundle\Entity\Interfaces\UrlParameterInterface  $urlParameter */
            foreach ($urlParametersByDomainAndLanguage->getUrlParameters() as $urlParameter)
            {
              if($urlParameter->getPath()) {
                $uri = $australRouting->getUrl("austral_website_page", $urlParameter, array('_locale'=>$langue));
              }
              else {
                $uri = $australRouting->getUrl("austral_website_homepage", $urlParameter, array('_locale'=>$langue));
              }
              $cacheKey = 'md'.hash('sha256', $uri);

              $cacheEnabled = $httpCacheEnabledChecker
                ->setEnabledByUrl($urlParameter->getInCacheEnabled())
                ->checkByDomainAndUri($urlParametersByDomainAndLanguage->getDomain()->getDomain(), $uri);

              $urlsByCache[$urlParametersByDomainAndLanguage->getDomain()->getDomain()][$langue][$uri] = array(
                "status"  =>  file_exists($httpCache->getStore()->getPath($cacheKey)),
                "enabled" =>  $cacheEnabled,
                "key"     =>  $cacheKey
              );
              if(!$cacheEnabled)
              {
                $urlsDisabled++;
              }
              $urlsTotal++;
              if($urlsByCache[$urlParametersByDomainAndLanguage->getDomain()->getDomain()][$langue][$uri]["status"] === true)
              {
                $urlsInCache++;
              }
            }
          }
        }
      }
    }
    $listAdminEvent->getTemplateParameters()->addParameters("urlsCaches", array(
      "total"       =>  $urlsTotal,
      "inCache"     =>  $urlsInCache,
      "urls"        =>  $urlsByCache,
      "disabled"    =>  $urlsDisabled
    ));

    $listAdminEvent->getTemplateParameters()->addParameters("actions", array(
      "cacheClear"  =>  new Action("clear_http_cache", "actions.clear_http_cache",
        $this->module->getChildren()["cache/purge-all"]->generateUrl(),
        null,
        array(
          "attr"    =>  array(
            "data-click-actions"    =>  "reload",
            "data-reload-elements-key"  =>  "container"
          ),
          "translateParameters" => array(
            "module_name"     =>  $this->module->translateSingular(),
            "module_gender"   =>  $this->module->translateGenre()
          ),
          "translateDomain" =>  "austral"
        )
      )
    ));

    $listAdminEvent->getTemplateParameters()->addParameters("moduleCachePurgeAll", $this->module->getChildren()["cache/purge-all"]);
    $listAdminEvent->getTemplateParameters()->setPath("@AustralCache/Admin/Module/cache.html.twig");
  }

  /**
   * @param ListAdminEvent $listAdminEvent
   *
   */
  public function purgeAll(ListAdminEvent $listAdminEvent)
  {
    if($uri = $listAdminEvent->getRequest()->query->get("uri"))
    {
      $request = Request::create($uri);
      $this->container->get("http_cache")->purge($request);
    }
    else
    {
      $this->container->get("http_cache")->purgeAll();
    }
    $listAdminEvent->getAdminHandler()->setRedirectUrl($this->module->getParent()->generateUrl());
  }



}