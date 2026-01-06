<?php

namespace Drupal\carbon_design_system_views\Plugin\views\style;

use Drupal\Core\form\FormStateInterface;

/**
 * Style plugin for a basic Carbon data table.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "carbon_datatable_basic",
 *   title = @Translation("Carbon Data Table - Basic"),
 *   help = @Translation("A simple Carbon data table without advanced features."),
 *   theme = "views_view_carbon_datatable",
 *   display_types = {"normal"}
 * )
 */
class CarbonDataTableBasic extends CarbonDataTable {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Override defaults for basic table.
    $options['use_zebra_styles'] = ['default' => TRUE];
    $options['sticky_header'] = ['default' => FALSE];
    $options['sortable'] = ['default' => FALSE];
    $options['batch_actions'] = ['default' => FALSE];
    $options['row_actions'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Hide advanced options for basic table.
    $form['sortable']['#access'] = FALSE;
    $form['batch_actions']['#access'] = FALSE;
    $form['row_actions']['#access'] = FALSE;
  }

}
