<?php

namespace Drupal\entra_jwt_token;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for managing Entra JWT tokens.
 */
class EntraJwtTokenService {

  /**
   * The session service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * The temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs an EntraJwtTokenService object.
   */
  public function __construct(SessionInterface $session, PrivateTempStoreFactory $temp_store_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->session = $session;
    $this->tempStore = $temp_store_factory->get('entra_jwt_token');
    $this->logger = $logger_factory->get('entra_jwt_token');
  }

  /**
   * Store JWT token (ID token).
   */
  public function storeJwtToken($token) {
    try {
      $this->session->set('entra_jwt_token', $token);
      $this->tempStore->set('jwt_token', $token);
      $this->logger->info('JWT token stored successfully');
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to store JWT token: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Store access token.
   */
  public function storeAccessToken($token) {
    try {
      $this->session->set('entra_access_token', $token);
      $this->tempStore->set('access_token', $token);
      $this->logger->info('Access token stored successfully');
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to store access token: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Get JWT token (ID token).
   */
  public function getJwtToken() {
    $token = $this->session->get('entra_jwt_token');
    if (!$token) {
      $token = $this->tempStore->get('jwt_token');
    }

    if ($token && $this->isTokenExpiringSoon($token)) {
      $this->logger->warning('JWT token is expiring soon (within 5 minutes)');
    }

    return $token;
  }

  /**
   * Get access token.
   */
  public function getAccessToken() {
    $token = $this->session->get('entra_access_token');
    if (!$token) {
      $token = $this->tempStore->get('access_token');
    }

    if ($token && $this->isTokenExpiringSoon($token)) {
      $this->logger->warning('Access token is expiring soon (within 5 minutes)');
    }

    return $token;
  }

  /**
   * Check if token is expiring soon (within 5 minutes).
   */
  protected function isTokenExpiringSoon($token) {
    try {
      $parts = explode('.', $token);
      if (count($parts) === 3) {
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
        if (isset($payload['exp'])) {
          $expires_at = $payload['exp'];
          $current_time = time();
          $time_until_expiry = $expires_at - $current_time;

          // Return true if expiring within 5 minutes (300 seconds)
          return $time_until_expiry < 300;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to check token expiry: @message', ['@message' => $e->getMessage()]);
    }

    return FALSE;
  }

  /**
   * Clear all stored tokens.
   */
  public function clearTokens() {
    $this->session->remove('entra_jwt_token');
    $this->session->remove('entra_access_token');
    $this->tempStore->delete('jwt_token');
    $this->tempStore->delete('access_token');
    $this->logger->info('All tokens cleared');
  }

}
