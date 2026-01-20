<?php

namespace Drupal\external_entities_suitecrm\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'form_mode_links_widget' widget.
 *
 * @FieldWidget(
 *   id = "form_mode_links_widget",
 *   label = @Translation("Form Mode Links Configuration"),
 *   field_types = {
 *     "form_mode_links"
 *   }
 * )
 */
class FormModeLinksWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // This field doesn't need a widget on the entity edit form
    // The configuration is done at the field settings level.
    $element['value'] = [
      '#type' => 'value',
      '#value' => '',
    ];

    return $element;
  }

}
