<?php

namespace Drupal\entra_jwt_token;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

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
   * The settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs an EntraJwtTokenService object.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The temp store factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal settings.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   */
  public function __construct(
    SessionInterface $session,
    PrivateTempStoreFactory $temp_store_factory,
    LoggerChannelFactoryInterface $logger_factory,
    Settings $settings,
    ClientInterface $http_client,
  ) {
    $this->session = $session;
    $this->tempStore = $temp_store_factory->get('entra_jwt_token');
    $this->logger = $logger_factory->get('entra_jwt_token');
    $this->settings = $settings;
    $this->httpClient = $http_client;
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

          // Return true if expiring within 5 minutes (300 seconds).
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
   * Check if token is expired.
   */
  public function isTokenExpired($token) {
    try {
      $parts = explode('.', $token);
      if (count($parts) === 3) {
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
        if (isset($payload['exp'])) {
          $expires_at = $payload['exp'];
          $current_time = time();

          // Token is expired if current time is past expiry.
          return $current_time >= $expires_at;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to check token expiry: @message', ['@message' => $e->getMessage()]);
    }

    // Assume expired if we can't parse it.
    return TRUE;
  }

  /**
   * Get time until token expiry in seconds.
   *
   * @return int|null
   *   Seconds until expiry, or NULL if token invalid/missing.
   */
  public function getTimeUntilExpiry($token = NULL) {
    if (!$token) {
      $token = $this->getJwtToken();
    }

    if (!$token) {
      return NULL;
    }

    try {
      $parts = explode('.', $token);
      if (count($parts) === 3) {
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
        if (isset($payload['exp'])) {
          $expires_at = $payload['exp'];
          $current_time = time();

          return $expires_at - $current_time;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get token expiry: @message', ['@message' => $e->getMessage()]);
    }

    return NULL;
  }

  /**
   * Renew JWT tokens using stored refresh token.
   *
   * @return bool
   *   TRUE if renewal successful, FALSE otherwise.
   */
  public function renewToken() {
    // Get refresh token from session/storage.
    $refresh_token = $this->session->get('entra_refresh_token');
    if (!$refresh_token) {
      $refresh_token = $this->tempStore->get('refresh_token');
    }

    if (!$refresh_token) {
      $this->logger->warning('Cannot renew token: No refresh token available');
      return FALSE;
    }

    try {
      // Get configuration from injected settings service.
      $client_id = $this->settings->get('OPENID_CLIENT_ID');
      $client_secret = $this->settings->get('OPENID_CLIENT_SECRET');
      $token_endpoint = $this->settings->get('TOKEN_ENDPOINT');

      if (!$client_id || !$client_secret || !$token_endpoint) {
        $this->logger->error('Cannot renew token: Missing OPENID_CLIENT_ID, OPENID_CLIENT_SECRET, or TOKEN_ENDPOINT in settings.php');
        return FALSE;
      }

      // Use injected httpClient.
      $response = $this->httpClient->post($token_endpoint, [
        'form_params' => [
          'client_id' => $client_id,
          'client_secret' => $client_secret,
          'grant_type' => 'refresh_token',
          'refresh_token' => $refresh_token,
          'scope' => 'openid offline_access api://' . $client_id . '/access_as_user',
        ],
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (isset($data['id_token']) && isset($data['access_token'])) {
        // Store new tokens.
        $this->storeJwtToken($data['id_token']);
        $this->storeAccessToken($data['access_token']);

        // Store new refresh token if provided.
        if (isset($data['refresh_token'])) {
          $this->storeRefreshToken($data['refresh_token']);
        }

        $this->logger->info('Successfully renewed JWT tokens');
        return TRUE;
      }

      $this->logger->error('Token renewal response missing expected tokens');
      return FALSE;

    }
    catch (\Exception $e) {
      $this->logger->error('Failed to renew token: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Store refresh token.
   */
  public function storeRefreshToken($token) {
    try {
      $this->session->set('entra_refresh_token', $token);
      $this->tempStore->set('refresh_token', $token);
      $this->logger->info('Refresh token stored successfully');
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to store refresh token: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Get refresh token.
   */
  public function getRefreshToken() {
    $token = $this->session->get('entra_refresh_token');
    if (!$token) {
      $token = $this->tempStore->get('refresh_token');
    }
    return $token;
  }

  /**
   * Clear all stored tokens.
   */
  public function clearTokens() {
    $this->session->remove('entra_jwt_token');
    $this->session->remove('entra_access_token');
    $this->session->remove('entra_refresh_token');
    $this->tempStore->delete('jwt_token');
    $this->tempStore->delete('access_token');
    $this->tempStore->delete('refresh_token');
    $this->logger->info('All tokens cleared');
  }

}
