<?php

namespace Drupal\carbon_design_system_views\Plugin\views\style;

use Drupal\Core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render a Carbon Design System data table.
 *
 * @ViewsStyle(
 * id = "carbon_datatable",
 * title = @Translation("Carbon Data Table"),
 * help = @Translation("Displays rows as a Carbon Design System data table."),
 * theme = "views_view_carbon_datatable",
 * display_types = {"normal"}
 * )
 */
class CarbonDataTable extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['size'] = ['default' => 'lg'];
    $options['use_zebra_styles'] = ['default' => TRUE];
    $options['sticky_header'] = ['default' => FALSE];
    $options['sortable'] = ['default' => TRUE];
    $options['batch_actions'] = ['default' => FALSE];
    $options['row_actions'] = ['default' => FALSE];
    $options['title'] = ['default' => ''];
    $options['description'] = ['default' => ''];
    $options['widths'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['carbon_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Carbon Data Table Settings'),
      '#open' => TRUE,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table Title'),
      '#default_value' => $this->options['title'],
      '#fieldset' => 'carbon_settings',
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table Description'),
      '#default_value' => $this->options['description'],
      '#fieldset' => 'carbon_settings',
    ];

    $form['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Table Size'),
      '#options' => [
        'xs' => $this->t('Extra Small'),
        'sm' => $this->t('Small'),
        'md' => $this->t('Medium'),
        'lg' => $this->t('Large'),
      ],
      '#default_value' => $this->options['size'],
      '#fieldset' => 'carbon_settings',
    ];

    $form['row_actions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable row actions (Overflow Menu)'),
      '#default_value' => $this->options['row_actions'],
      '#fieldset' => 'carbon_settings',
    ];

    $form['column_widths'] = [
      '#type' => 'details',
      '#title' => $this->t('Column Widths'),
      '#open' => FALSE,
      '#fieldset' => 'column_widths',
    ];

    foreach ($this->displayHandler->getHandlers('field') as $id => $field) {
      if (empty($field->options['exclude'])) {
        $form['widths'][$id] = [
          '#type' => 'textfield',
          '#title' => $this->t('Width for %label', ['%label' => $field->label()]),
          '#default_value' => $this->options['widths'][$id] ?? '',
          '#size' => 10,
          '#fieldset' => 'column_widths',
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $headers = [];
    $rows = [];
    $widths = $this->options['widths'] ?? [];

    foreach ($this->view->field as $id => $field) {
      if (!empty($field->options['exclude'])) {
        continue;
      }
      $headers[] = [
        'key' => $id,
        'header' => $field->label() ?: $id,
        'width' => !empty($widths[$id]) ? $widths[$id] : 'auto',
      ];
    }

    foreach ($this->view->result as $row) {
      $row_data = [];
      $entity = $row->_entity ?? NULL;

      foreach ($this->view->field as $id => $field) {
        if (!empty($field->options['exclude'])) {
          continue;
        }
        $row_data[$id] = $field->advancedRender($row);
      }

      if ($entity && $this->options['row_actions']) {
        $actions = [];
        if ($entity->access('update')) {
          $actions['edit'] = $entity->toUrl('edit-form')->toString();
        }
        if ($entity->access('delete')) {
          $actions['delete'] = $entity->toUrl('delete-form')->toString();
        }
        $row_data['dynamic_actions'] = $actions;
      }
      $rows[] = $row_data;
    }

    return [
      '#type' => 'component',
      '#component' => 'carbon_design_system:datatable',
      '#props' => [
        'title' => $this->options['title'],
        'description' => $this->options['description'],
        'size' => $this->options['size'],
        'headers' => $headers,
        'rows' => $rows,
        'rowActions' => $this->options['row_actions'],
      ],
    ];
  }

}
