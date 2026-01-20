<?php

namespace Drupal\external_entities_suitecrm\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $config = $this->configFactory->get('external_entities.external_entity_type.suitecrm_case');
    $add_form_mode = $config->get('third_party_settings.external_entities_suitecrm.add_form_mode');

    // Only modify if a custom form mode is configured.
    if (!$add_form_mode || $add_form_mode === 'default') {
      return;
    }

    // Modify the add-form route to use the custom form mode.
    if ($route = $collection->get('entity.suitecrm_case.add_form')) {
      $route->setDefault('_entity_form', 'suitecrm_case.' . $add_form_mode);

      // Update the permission requirement to match the form mode.
      $permission = "use suitecrm_case.{$add_form_mode} form mode";
      $route->setRequirement('_permission', $permission);
    }
  }

}
