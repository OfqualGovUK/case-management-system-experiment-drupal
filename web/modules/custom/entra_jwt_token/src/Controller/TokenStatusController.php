<?php

namespace Drupal\entra_jwt_token\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\entra_jwt_token\EntraJwtTokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying JWT token status.
 */
class TokenStatusController extends ControllerBase {

  /**
   * The Entra JWT token service.
   *
   * @var \Drupal\entra_jwt_token\EntraJwtTokenService
   */
  protected $tokenService;

  /**
   * Constructs a TokenStatusController object.
   *
   * @param \Drupal\entra_jwt_token\EntraJwtTokenService $token_service
   *   The Entra JWT token service.
   */
  public function __construct(EntraJwtTokenService $token_service) {
    $this->tokenService = $token_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entra_jwt_token.token_service')
    );
  }

  /**
   * Displays token status.
   *
   * @return array
   *   A render array.
   */
  public function status() {
    $output = '<h2>Entra JWT Token Status</h2>';

    $jwt = $this->tokenService->getJwtToken();
    $access = $this->tokenService->getAccessToken();

    $output .= '<h3>Token Availability:</h3>';
    $output .= '<p><strong>JWT Token (ID):</strong> ' . ($jwt ? 'Available' : 'Not Available') . '</p>';
    $output .= '<p><strong>Access Token:</strong> ' . ($access ? 'Available' : 'Not Available') . '</p>';

    if ($access) {
      $parts = explode('.', $access);
      if (count($parts) === 3) {
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);

        $output .= '<h3>Access Token Details:</h3>';
        $output .= '<ul>';
        $output .= '<li><strong>Scopes (scp):</strong> ' . ($payload['scp'] ?? 'N/A') . '</li>';

        if (isset($payload['exp'])) {
          $expires_at = date('d M Y H:i:s', $payload['exp']);
          $time_until = $payload['exp'] - time();
          $minutes_until = floor($time_until / 60);

          $output .= '<li><strong>Expires:</strong> ' . $expires_at;
          if ($time_until > 0) {
            $output .= ' (' . $minutes_until . ' minutes remaining)';
          }
          else {
            $output .= ' (EXPIRED)';
          }
          $output .= '</li>';
        }

        $output .= '</ul>';

        // Check audience.
        $expected_audience = 'api://' . getenv('OPENID_CLIENT_ID');
        if (isset($payload['aud']) && $payload['aud'] === $expected_audience) {
          $output .= '<strong>Correct Audience</strong> Token is valid for SuiteCRM API.';
        }
        else {
          $output .= '<strong>Wrong Audience</strong> Token audience does not match SuiteCRM API.';
        }
      }
    }

    if (!$jwt && !$access) {
      $output .= '<p><strong>Note:</strong> Tokens are only available when logged in via Entra SSO.</p>';
      $output .= '<p>Please log out and log back in using your Entra ID credentials to obtain tokens.</p>';
    }

    return ['#markup' => $output];
  }

}
