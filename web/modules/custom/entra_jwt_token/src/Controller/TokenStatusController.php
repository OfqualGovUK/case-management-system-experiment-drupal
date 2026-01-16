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
    $jwt = $this->tokenService->getJwtToken();
    $access = $this->tokenService->getAccessToken();

    $build = [];

    $build['title'] = [
      '#markup' => '<h2>' . $this->t('Entra JWT Token Status') . '</h2>',
    ];

    // Token availability
    $build['availability'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Token Availability'),
    ];

    $jwt_status = $jwt ? $this->t('Available') : $this->t('Not Available');
    $access_status = $access ? $this->t('Available') : $this->t('Not Available');

    $build['availability']['jwt'] = [
      '#markup' => '<p><strong>' . $this->t('JWT Token (ID):') . '</strong> ' . $jwt_status . '</p>',
    ];

    $build['availability']['access'] = [
      '#markup' => '<p><strong>' . $this->t('Access Token:') . '</strong> ' . $access_status . '</p>',
    ];

    // Access token details
    if ($access) {
      $parts = explode('.', $access);
      if (count($parts) === 3) {
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);

        $build['details'] = [
          '#type' => 'container',
          '#title' => $this->t('Access Token Details'),
        ];

        $details = '<ul>';
        $details .= '<li><strong>' . $this->t('Scopes (scp):') . '</strong> ' . ($payload['scp'] ?? 'N/A') . '</li>';

        if (isset($payload['exp'])) {
          $expires_at = date('d M Y H:i:s', $payload['exp']);
          $time_until = $this->tokenService->getTimeUntilExpiry($access);
          $minutes_until = floor($time_until / 60);

          $expiry_message = '';

          if ($time_until <= 0) {
            $expiry_message = $this->t('EXPIRED - Please log in again');
            $this->messenger()->addError($this->t('Your authentication token has expired. Please log out and log back in.'));
          }
          elseif ($time_until < 300) {
            $expiry_message = $this->t('Expiring soon (@minutes minutes remaining)', ['@minutes' => $minutes_until]);
            $this->messenger()->addWarning($this->t('Your authentication token will expire in @minutes minutes. Please save your work and refresh your login soon.', ['@minutes' => $minutes_until]));
          }
          else {
            $expiry_message = $this->t('Valid (@minutes minutes remaining)', ['@minutes' => $minutes_until]);
          }

          $details .= '<li><strong>' . $this->t('Expires:') . '</strong> ' . $expires_at . '</li>';
          $details .= '<li><strong>' . $this->t('Status:') . '</strong>' . $expiry_message . '</li>';
        }

        $details .= '</ul>';

        // Check audience
        $expected_audience = 'api://' . getenv('OPENID_CLIENT_ID');
        if (isset($payload['aud']) && $payload['aud'] === $expected_audience) {
          $details .= '<p><strong>' . $this->t('Correct Audience') . '</strong> ' . $this->t('Token is valid for SuiteCRM API.') . '</p>';
        }
        else {
          $details .= '<p><strong>' . $this->t('Wrong Audience') . '</strong> ' . $this->t('Token audience does not match SuiteCRM API.') . '</p>';
          $this->messenger()->addError($this->t('Token audience mismatch. Expected: @expected, Got: @actual', [
            '@expected' => $expected_audience,
            '@actual' => $payload['aud'] ?? 'N/A',
          ]));
        }

        $build['details']['content'] = [
          '#markup' => $details,
          '#attached' => [
            'library' => ['system/admin'],
          ],
        ];
      }
    }

    // No tokens available
    if (!$jwt && !$access) {
      $this->messenger()->addWarning($this->t('No authentication tokens found. You may need to log in via Entra SSO.'));

      $build['note'] = [
        '#type' => 'container',
        '#title' => $this->t('How to Obtain Tokens'),
      ];

      $build['note']['content'] = [
        '#markup' => '<p>' . $this->t('Tokens are only available when logged in via Entra SSO.') . '</p>' .
                     '<p>' . $this->t('Please log out and log back in using your Entra ID credentials to obtain tokens.') . '</p>',
      ];
    }

    return $build;
  }

}
