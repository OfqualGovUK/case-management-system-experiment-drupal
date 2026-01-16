<?php

namespace Drupal\external_entities_suitecrm\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Get the configured form mode from third-party settings
    $config = \Drupal::config('external_entities.external_entity_type.suitecrm_case');
    $add_form_mode = $config->get('third_party_settings.external_entities_suitecrm.add_form_mode');
    
    // Only modify if a custom form mode is configured
    if (!$add_form_mode || $add_form_mode === 'default') {
      return;
    }
    
    // Modify the add-form route to use the custom form mode
    if ($route = $collection->get('entity.suitecrm_case.add_form')) {
      $route->setDefault('_entity_form', 'suitecrm_case.' . $add_form_mode);
      
      // Update the permission requirement to match the form mode
      $permission = "use suitecrm_case.{$add_form_mode} form mode";
      $route->setRequirement('_permission', $permission);
    }
  }

}
