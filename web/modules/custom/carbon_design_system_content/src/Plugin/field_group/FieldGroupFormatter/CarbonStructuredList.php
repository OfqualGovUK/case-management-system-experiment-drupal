<?php

namespace Drupal\carbon_design_system_content\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'carbon_structured_list' formatter.
 *
 * @FieldGroupFormatter(
 * id = "carbon_structured_list",
 * label = @Translation("Carbon Structured List"),
 * description = @Translation("Displays fields in a Carbon Design System Structured List format."),
 * supported_contexts = {
 * "view",
 * }
 * )
 */
class CarbonStructuredList extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);

    $settings = $this->getSettings();
    $entity = $rendering_object['#' . $rendering_object['#entity_type']];

    $headers = [];
    if (!empty($settings['custom_headers'])) {
      $custom_headers = array_filter(array_map('trim', explode("\n", $settings['custom_headers'])));
      if (!empty($custom_headers) && count($custom_headers) === 2) {
        $headers = $custom_headers;
      }
    }

    $rows = [];
    foreach ($this->group->children as $field_name) {
      if ($entity->hasField($field_name) && isset($element[$field_name])) {
        $field_definition = $entity->get($field_name)->getFieldDefinition();
        $label = $field_definition->getLabel();
        $field_type = $field_definition->getType();

        $element[$field_name]['#label_display'] = 'hidden';

        // Use the standard renderer for the field value.
        $rows[] = [
          'label' => ($field_type === 'form_mode_links') ? '' : $label,
          'value' => $element[$field_name],
        ];

        $element[$field_name]['#printed'] = TRUE;
      }
    }

    $group_anchor_id = 'group-' . str_replace('_', '-', $this->group->group_name);

    if (!empty($this->group->label)) {
      $element['group_label'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->group->label,
        '#weight' => -10,
        '#attributes' => [
          'id' => $group_anchor_id,
          'class' => ['field-group-heading'],
        ],
      ];
    }

    $element['structured_list'] = [
      '#type' => 'component',
      '#component' => 'carbon_design_system:structured_list',
      '#props' => [
        'headers' => $headers,
        'rows' => $rows,
        'size' => $settings['size'] ?? 'lg',
        'selection' => !empty($settings['selection']),
        'flush' => !empty($settings['flush']),
      ],
      '#weight' => -5,
    ];

    $element += [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['field-group-carbon-structured-list'],
      ],
    ];

    $element['#attached']['library'][] = 'carbon_design_system/structured_list_custom';
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = parent::settingsForm();
    unset($form['id'], $form['classes']);

    $form['size'] = [
      '#type' => 'select',
      '#title' => $this->t('List size'),
      '#options' => [
        'sm' => $this->t('Small'),
        'md' => $this->t('Medium'),
        'lg' => $this->t('Large'),
      ],
      '#default_value' => $this->getSetting('size') ?? 'lg',
    ];

    $form['selection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable row selection'),
      '#default_value' => $this->getSetting('selection') ?? FALSE,
    ];

    $form['flush'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Flush alignment'),
      '#default_value' => $this->getSetting('flush') ?? FALSE,
      '#states' => [
        'disabled' => [':input[name*="[selection]"]' => ['checked' => TRUE]],
      ],
    ];

    $form['custom_headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom column headers'),
      '#default_value' => $this->getSetting('custom_headers') ?? '',
      '#rows' => 2,
    ];

    $form['show_in_toc'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show in table of contents'),
      '#default_value' => $this->getSetting('show_in_toc') ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();

    if (!empty($settings['show_in_toc'])) {
      $summary[] = $this->t('Shown in page TOC');
    }

    return $summary;
  }

}
