<?php

namespace Drupal\ofqual_custom_notify\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Component\Serialization\Json;

/**
 * Provides a service to send notifications via external API.
 */
class NotificationService
{

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs the NotificationService.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    ClientInterface $httpClient,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->httpClient = $httpClient;
    $this->logger = $loggerFactory->get('ofqual_custom_notify');
  }

  /**
   * Sends a notification payload to the external API.
   *
   * @param array $notificationPayload
   *   The notification payload.
   *
   * @return bool
   *   TRUE if the notification was sent successfully, FALSE otherwise.
   */
  public function send(array $notificationPayload): bool
  {
    try {
      $requiredEnvVars = [
        'NOTIFICATION_CLIENT_ID',
        'NOTIFICATION_CLIENT_SECRET',
        'NOTIFICATION_GRANT_TYPE',
        'NOTIFICATION_SCOPE',
        'NOTIFICATION_API_URL',
        'AZURE_TENANT_ID'
      ];

      $apiCredentials = [];

      foreach ($requiredEnvVars as $envVar) {
        $value = getenv($envVar);
        if (empty($value)) {
          $this->logger->error('Missing environment variable: @var', ['@var' => $envVar]);
          return FALSE;
        }
        $apiCredentials[strtolower($envVar)] = $value;
      }

      // Get access token
      $accessTokenResponse = $this->httpClient->post('https://login.microsoftonline.com/' . $apiCredentials['azure_tenant_id'] . '/oauth2/v2.0/token', [
        'form_params' => [
          'client_id'     => $apiCredentials['notification_client_id'],
          'client_secret' => $apiCredentials['notification_client_secret'],
          'grant_type'    => $apiCredentials['notification_grant_type'],
          'scope'         => $apiCredentials['notification_scope'],
        ],
      ]);

      $accessTokenData = Json::decode($accessTokenResponse->getBody()->getContents());
      if (empty($accessTokenData['access_token'])) {
        $this->logger->error('Failed to retrieve access token.');
        return FALSE;
      }

      // Send notification
      $notificationResponse = $this->httpClient->post($apiCredentials['notification_api_url'] . '/notifications', [
        'json' => $notificationPayload,
        'timeout' => 10,
        'headers' => [
          'Authorization' => 'Bearer ' . $accessTokenData['access_token'],
          'Accept'        => 'application/json',
          'Content-Type'  => 'application/json',
        ],
      ]);

      $this->logger->notice('Notification sent successfully.');
      return $notificationResponse->getStatusCode() === 200;
    } catch (\Exception $exception) {
      $this->logger->error('Notification sending failed: @message', ['@message' => $exception->getMessage()]);
      return FALSE;
    }
  }
}
