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

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache as BaseHttpCache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpCache\SurrogateInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class HttpCache
 *
 * @package Austral\CacheBundle\Services
 *
 * @author  Matthieu Beurel <matthieu@austral.dev>
 * @final
 */
class HttpCache extends BaseHttpCache
{

  /**
   * @var string|null
   */
  protected ?string $purgeStatus = null;
  protected ContainerInterface $container;

  public function __construct(ContainerInterface $container, KernelInterface $kernel, $cache = null, SurrogateInterface $surrogate = null, array $options = null)
  {
    $this->container = $container;
    parent::__construct($kernel, $cache, $surrogate, $options);
  }

  /**
   * handle
   *
   * @param Request $request
   * @param int $type
   * @param bool $catch
   * @return Response
   * @throws \Exception
   */
  public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true)
  {
    if (HttpKernelInterface::MAIN_REQUEST === $type) {
      $this->kernel->boot();
      $request->headers->set("x-austral-cache-checked", true);
      $this->container->get('request_stack')->push($request);
      $event = new RequestEvent($this, $request, $type);
      $this->container->get('event_dispatcher')->dispatch($event, KernelEvents::REQUEST);
      if($this->container->get("security.authorization_checker")->isGranted("ROLE_ADMIN_ACCESS"))
      {
        $request->headers->remove("x-austral-cache-checked");
        $this->purge($request);
      }
      $request->headers->remove("x-austral-cache-checked");
    }
    return parent::handle($request, $type, $catch);
  }

  /**
   * invalidate
   *
   * @param Request $request
   * @param bool $catch
   * @return Response
   * @throws \Exception
   */
  protected function invalidate(Request $request, bool $catch = false): Response
  {
    if ('PURGE' !== $request->getMethod()) {
      return parent::invalidate($request, $catch);
    }
    if ('127.0.0.1' !== $request->getClientIp()) {
      return new Response(
        'Invalid HTTP method',
        Response::HTTP_BAD_REQUEST
      );
    }

    $response = new Response();
    $this->purge($request);
    if ($this->purgeStatus === "success") {
      $response->setStatusCode(Response::HTTP_OK, 'Purged');
    } else {
      $response->setStatusCode(Response::HTTP_NOT_FOUND, 'Not found');
    }
    return $response;


  }

  /**
   * purge
   *
   * @param Request $request
   * @return HttpCache
   */
  public function purge(Request $request): HttpCache
  {
    $this->purgeStatus = $this->getStore()->purge($request->getUri()) ? "success": "not_success";
    return $this;
  }

  /**
   * purgeAll
   *
   * @return HttpCache
   */
  public function purgeAll(): HttpCache
  {
    try {
      $cacheDir = $this->cacheDir ?: $this->kernel->getCacheDir().'/http_cache';
      $filesystem = new Filesystem();
      $filesystem->remove($cacheDir);
      $this->purgeStatus = "success";
    } catch(\Exception $e) {
      $this->purgeStatus = "not_success";
    }
    return $this;
  }

  /**
   * isPurgeSuccess
   *
   * @return bool
   */
  public function getPurgeStatus(): bool
  {
    return $this->purgeStatus;
  }


}