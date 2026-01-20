<?php

namespace Drupal\external_entities_suitecrm\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'form_mode_links' field type.
 *
 * @FieldType(
 *   id = "form_mode_links",
 *   label = @Translation("Form Mode Links"),
 *   description = @Translation("Displays links to edit this entity using different form modes"),
 *   default_widget = "form_mode_links_widget",
 *   default_formatter = "form_mode_links_formatter",
 *   no_ui = FALSE
 * )
 */
class FormModeLinksItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // We don't actually store any data, but we need at least one property.
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Value'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'varchar',
          'length' => 1,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Field is never empty - it always renders if user has permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [] + parent::defaultFieldSettings();
  }

}
