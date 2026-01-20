<?php

namespace Drupal\entra_jwt_token\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\entra_jwt_token\EntraJwtTokenService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to check and renew JWT tokens automatically.
 */
class TokenRenewalSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The JWT token service.
   *
   * @var \Drupal\entra_jwt_token\EntraJwtTokenService
   */
  protected $tokenService;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a TokenRenewalSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\entra_jwt_token\EntraJwtTokenService $token_service
   *   The JWT token service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    EntraJwtTokenService $token_service,
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->currentUser = $current_user;
    $this->tokenService = $token_service;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run early in the request, but after authentication.
    $events[KernelEvents::REQUEST][] = ['onRequest', 20];
    return $events;
  }

  /**
   * Check and renew tokens on each request.
   */
  public function onRequest(RequestEvent $event) {
    // Only check main requests.
    if (!$event->isMainRequest()) {
      return;
    }

    // Check if auto-renewal is enabled using injected config factory.
    $config = $this->configFactory->get('entra_jwt_token.settings');
    $auto_renewal_enabled = $config->get('enable_auto_renewal') ?? TRUE;

    if (!$auto_renewal_enabled) {
      return;
    }

    // Only check for authenticated users.
    if (!$this->currentUser->isAuthenticated()) {
      return;
    }

    $token = $this->tokenService->getAccessToken();

    if (!$token) {
      return;
    }

    $renewal_threshold_minutes = $config->get('renewal_threshold') ?? 10;
    $renewal_threshold_seconds = $renewal_threshold_minutes * 60;
    $time_until_expiry = $this->tokenService->getTimeUntilExpiry($token);

    // If token expires within threshold, try to renew.
    if ($time_until_expiry !== NULL && $time_until_expiry < $renewal_threshold_seconds && $time_until_expiry > 0) {
      $this->logger->info('Token expiring soon (@seconds seconds). Attempting renewal...', [
        '@seconds' => $time_until_expiry,
      ]);

      // Attempt to renew token.
      $renewed = $this->tokenService->renewToken();

      if ($renewed && $config->get('log_renewals')) {
        $this->logger->info('Token successfully renewed automatically');
      }
    }
    // If token is already expired.
    elseif ($time_until_expiry !== NULL && $time_until_expiry <= 0) {
      $this->logger->warning('Token has expired. Attempting renewal...');
      $this->tokenService->renewToken();
    }
  }

}
