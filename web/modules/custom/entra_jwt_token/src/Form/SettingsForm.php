<?php

namespace Drupal\entra_jwt_token\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Entra JWT Token settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entra_jwt_token_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['entra_jwt_token.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('entra_jwt_token.settings');

    $form['auto_renewal'] = [
      '#type' => 'container',
      '#title' => $this->t('Automatic Token Renewal'),
    ];

    $form['auto_renewal']['enable_auto_renewal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic token renewal'),
      '#description' => $this->t('Automatically renew JWT tokens before they expire (requires refresh token).'),
      '#default_value' => $config->get('enable_auto_renewal') ?? TRUE,
    ];

    $form['auto_renewal']['renewal_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Renewal threshold (minutes)'),
      '#description' => $this->t('Renew tokens when they will expire within this many minutes.'),
      '#default_value' => $config->get('renewal_threshold') ?? 10,
      '#min' => 1,
      '#max' => 60,
      '#states' => [
        'visible' => [
          ':input[name="enable_auto_renewal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['session'] = [
      '#type' => 'container',
      '#title' => $this->t('Session Configuration'),
    ];

    $form['session']['enable_session_timeout'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable session timeout'),
      '#description' => $this->t('Automatically log out users after a period of inactivity.'),
      '#default_value' => $config->get('enable_session_timeout') ?? FALSE,
    ];

    $form['session']['session_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Session timeout (minutes)'),
      '#description' => $this->t('Log out user after this many minutes of inactivity.'),
      '#default_value' => $config->get('session_timeout') ?? 60,
      '#min' => 5,
      '#max' => 1440,
      '#states' => [
        'visible' => [
          ':input[name="enable_session_timeout"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['session']['session_warning'] = [
      '#type' => 'number',
      '#title' => $this->t('Warning before timeout (minutes)'),
      '#description' => $this->t('Show a warning this many minutes before session expires.'),
      '#default_value' => $config->get('session_warning') ?? 5,
      '#min' => 1,
      '#max' => 30,
      '#states' => [
        'visible' => [
          ':input[name="enable_session_timeout"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['logging'] = [
      '#type' => 'container',
      '#title' => $this->t('Logging'),
    ];

    $form['logging']['log_renewals'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log token renewals'),
      '#description' => $this->t('Log to watchdog when tokens are automatically renewed.'),
      '#default_value' => $config->get('log_renewals') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('entra_jwt_token.settings')
      ->set('enable_auto_renewal', $form_state->getValue('enable_auto_renewal'))
      ->set('renewal_threshold', $form_state->getValue('renewal_threshold'))
      ->set('enable_session_timeout', $form_state->getValue('enable_session_timeout'))
      ->set('session_timeout', $form_state->getValue('session_timeout'))
      ->set('session_warning', $form_state->getValue('session_warning'))
      ->set('log_renewals', $form_state->getValue('log_renewals'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
