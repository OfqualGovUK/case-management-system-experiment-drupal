<?php

namespace Drupal\external_entities_suitecrm\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'form_mode_links_formatter' formatter.
 *
 * @FieldFormatter(
 * id = "form_mode_links_formatter",
 * label = @Translation("Form Mode Links"),
 * field_types = {
 * "form_mode_links"
 * }
 * )
 */
class FormModeLinksFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Constructs a FormModeLinksFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    EntityDisplayRepositoryInterface $entity_display_repository,
    AccountProxyInterface $current_user,
    RouteProviderInterface $route_provider,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityDisplayRepository = $entity_display_repository;
    $this->currentUser = $current_user;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_display.repository'),
      $container->get('current_user'),
      $container->get('router.route_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_default' => TRUE,
      'form_modes' => [],
      'link_class' => 'button',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['show_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show default edit link'),
      '#default_value' => $this->getSetting('show_default'),
    ];

    $elements['link_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link CSS class'),
      '#default_value' => $this->getSetting('link_class'),
      '#description' => $this->t('CSS class to apply to links (e.g., "button" or "button button--primary")'),
    ];

    // Get available form modes for this entity type.
    $entity_type_id = $this->fieldDefinition->getTargetEntityTypeId();

    try {
      $form_modes = $this->entityDisplayRepository->getFormModes($entity_type_id);

      $form_mode_options = [];
      foreach ($form_modes as $form_mode_id => $form_mode_info) {
        $form_mode_options[$form_mode_id] = $form_mode_info['label'];
      }

      $elements['form_modes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Form modes to display'),
        '#options' => $form_mode_options,
        '#default_value' => $this->getSetting('form_modes') ?: [],
        '#description' => $this->t('Select which form mode edit links to display.'),
      ];
    }
    catch (\Exception $e) {
      $elements['form_modes'] = [
        '#markup' => $this->t('No form modes available for this entity type.'),
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if ($this->getSetting('show_default')) {
      $summary[] = $this->t('Show default edit link');
    }

    $form_modes = array_filter($this->getSetting('form_modes') ?: []);
    if (!empty($form_modes)) {
      $summary[] = $this->t('Form modes: @modes', [
        '@modes' => implode(', ', $form_modes),
      ]);
    }

    $link_class = $this->getSetting('link_class');
    if ($link_class) {
      $summary[] = $this->t('CSS class: @class', ['@class' => $link_class]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();

    // We only need to render once, not per field item.
    if (!$entity->access('update')) {
      return $elements;
    }

    $links = [];

    // Add default edit link if enabled.
    if ($this->getSetting('show_default') && $entity->hasLinkTemplate('edit-form')) {
      $links['default'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $entity->toUrl('edit-form'),
        '#attributes' => [
          'class' => [$this->getSetting('link_class'), 'button--primary'],
        ],
      ];
    }

    // Add form mode links.
    $selected_form_modes = array_filter($this->getSetting('form_modes') ?: []);

    if (!empty($selected_form_modes)) {
      try {
        $form_modes = $this->entityDisplayRepository->getFormModes($entity_type_id);

        foreach ($selected_form_modes as $form_mode_id) {
          if (!isset($form_modes[$form_mode_id])) {
            continue;
          }

          $form_mode_info = $form_modes[$form_mode_id];

          // Check form mode permission.
          $permission = "use {$entity_type_id}.{$form_mode_id} form mode";

          if (!$this->currentUser->hasPermission($permission)) {
            continue;
          }

          $route_name = "entity.{$entity_type_id}.edit_form.{$form_mode_id}";

          // Check if route exists.
          try {
            $this->routeProvider->getRouteByName($route_name);

            $links[$form_mode_id] = [
              '#type' => 'link',
              '#title' => $this->t('Edit: @mode', ['@mode' => $form_mode_info['label']]),
              '#url' => Url::fromRoute($route_name, [$entity_type_id => $entity->id()]),
              '#attributes' => [
                'class' => [$this->getSetting('link_class')],
              ],
            ];
          }
          catch (\Exception $e) {
            // Route doesn't exist, skip.
          }
        }
      }
      catch (\Exception $e) {
        // Service not available.
      }
    }

    // Render all links.
    if (!empty($links)) {
      $elements[0] = [
        '#theme' => 'item_list',
        '#items' => $links,
        '#attributes' => [
          'class' => ['form-mode-links'],
        ],
        '#cache' => [
          'contexts' => ['user.permissions'],
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

}
