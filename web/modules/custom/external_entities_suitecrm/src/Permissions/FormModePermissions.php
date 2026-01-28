<?php

namespace Drupal\external_entities_suitecrm\Permissions;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for form modes.
 */
class FormModePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a FormModePermissions object.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository) {
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_display.repository')
    );
  }

  /**
   * Returns an array of form mode permissions.
   *
   * @return array
   *   The form mode permissions.
   */
  public function permissions() {
    $permissions = [];

    // Define which entity types should have form mode permissions.
    $entity_types = ['suitecrm_case'];

    foreach ($entity_types as $entity_type_id) {
      // Add permission for default form mode.
      $permissions["use {$entity_type_id}.default form mode"] = [
        'title' => $this->t('Use default form mode for @entity_type', [
          '@entity_type' => ucwords(str_replace('_', ' ', $entity_type_id)),
        ]),
        'description' => $this->t('Allows using the default form mode when creating/editing @entity_type entities.', [
          '@entity_type' => str_replace('_', ' ', $entity_type_id),
        ]),
      ];

      // Get all form modes for this entity type.
      try {
        $form_modes = $this->entityDisplayRepository->getFormModes($entity_type_id);

        foreach ($form_modes as $form_mode_id => $form_mode_info) {
          $permissions["use {$entity_type_id}.{$form_mode_id} form mode"] = [
            'title' => $this->t('Use @form_mode form mode for @entity_type', [
              '@form_mode' => $form_mode_info['label'],
              '@entity_type' => ucwords(str_replace('_', ' ', $entity_type_id)),
            ]),
            'description' => $this->t('Allows using the @form_mode form mode when creating/editing @entity_type entities.', [
              '@form_mode' => $form_mode_info['label'],
              '@entity_type' => str_replace('_', ' ', $entity_type_id),
            ]),
          ];
        }
      }
      catch (\Exception $e) {
        // If entity type doesn't exist or has no form modes, continue.
        continue;
      }
    }

    return $permissions;
  }

}
