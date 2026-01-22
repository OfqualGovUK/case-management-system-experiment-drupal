<?php

namespace Drupal\carbon_design_system_content\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\field_group\FieldGroupFormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'carbon_structured_list' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "carbon_structured_list",
 *   label = @Translation("Carbon Structured List"),
 *   description = @Translation("Displays fields in a Carbon Design System Structured List format."),
 *   supported_contexts = {
 *     "view",
 *   }
 * )
 */
class CarbonStructuredList extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);

    // Get settings.
    $settings = $this->getSettings();

    // Get the entity.
    $entity = $rendering_object['#' . $rendering_object['#entity_type']];

    // For this use case, headers are optional.
    $headers = [];

    // Check for custom headers.
    if (!empty($settings['custom_headers'])) {
      $custom_headers = array_filter(array_map('trim', explode("\n", $settings['custom_headers'])));
      if (!empty($custom_headers) && count($custom_headers) === 2) {
        $headers = $custom_headers;
      }
    }

    // Build rows - each field is a row.
    $rows = [];

    foreach ($this->group->children as $field_name) {
      if ($entity->hasField($field_name) && isset($element[$field_name])) {
        $field_definition = $entity->get($field_name)->getFieldDefinition();
        $field_type = $field_definition->getType();
        $label = $field_definition->getLabel();

        // Hide the label in the field render.
        $element[$field_name]['#label_display'] = 'hidden';

        // Render the field value.
        $value = \Drupal::service('renderer')->renderPlain($element[$field_name]);

        // For form_mode_links fields, skip the label (just show the link).
        if ($field_type === 'form_mode_links') {
          // Don't add a row for form mode links - they'll be added to the previous row
          // Or just add the link without a label
          $rows[] = ['', $value];
        }
        else {
          // Add as a normal row: [Label, Value].
          $rows[] = [$label, $value];
        }

        // Mark field as printed so it doesn't render again.
        $element[$field_name]['#printed'] = TRUE;
      }
    }

    // Create the structured list component.
    $structured_list = [
      '#type' => 'component',
      '#component' => 'carbon_design_system:structured_list',
      '#props' => [
        'headers' => $headers,
        'rows' => $rows,
        'size' => $settings['size'] ?? 'lg',
        'selection' => !empty($settings['selection']),
        'flush' => !empty($settings['flush']),
      ],
    ];

    // Add the group label as a heading if it exists.
    if (!empty($this->group->label)) {
      $element['group_label'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->group->label,
        '#weight' => -10,
      ];
    }

    // Add the structured list.
    $element['structured_list'] = $structured_list;
    $element['structured_list']['#weight'] = -5;

    $element += [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['field-group-carbon-structured-list'],
      ],
    ];

    // Add the Custom Carbon structured list library.
    $element['#attached']['library'][] = 'carbon_design_system/structured_list_custom';
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = parent::settingsForm();

    // Remove id and classes fields that aren't needed
    if (isset($form['id'])) {
      unset($form['id']);
    }
    if (isset($form['classes'])) {
      unset($form['classes']);
    }

    $form['size'] = [
      '#type' => 'select',
      '#title' => $this->t('List size'),
      '#options' => [
        'sm' => $this->t('Small (Condensed)'),
        'md' => $this->t('Medium'),
        'lg' => $this->t('Large (Default)'),
      ],
      '#default_value' => $this->getSetting('size') ?? 'lg',
      '#description' => $this->t('The size/height of the structured list rows.'),
    ];

    $form['selection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable row selection'),
      '#default_value' => $this->getSetting('selection') ?? FALSE,
      '#description' => $this->t('Allow users to select rows (selectable variant).'),
    ];

    $form['flush'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Flush alignment'),
      '#default_value' => $this->getSetting('flush') ?? FALSE,
      '#description' => $this->t('Use flush alignment (no indentation). Not available with selection.'),
      '#states' => [
        'disabled' => [
          ':input[name*="[selection]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['custom_headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom column headers (optional)'),
      '#default_value' => $this->getSetting('custom_headers') ?? '',
      '#rows' => 2,
      '#description' => $this->t('Add column headers if desired. Enter exactly two lines (one for each column). Leave empty for no headers.'),
      '#placeholder' => "Field Name\nField Value",
    ];

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<p><strong>Note:</strong> Each field in this group will display as a separate row with the field label in the first column and the field value in the second column.</p>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();

    $size_labels = [
      'sm' => $this->t('Small'),
      'md' => $this->t('Medium'),
      'lg' => $this->t('Large'),
    ];

    $summary[] = $this->t('Size: @size', [
      '@size' => $size_labels[$settings['size'] ?? 'lg'],
    ]);

    if (!empty($settings['selection'])) {
      $summary[] = $this->t('Selection enabled');
    }

    if (!empty($settings['flush'])) {
      $summary[] = $this->t('Flush alignment');
    }

    $summary[] = $this->t('Each field displays as a row (Label | Value)');

    return $summary;
  }

}
