<?php

namespace Drupal\external_entities_suitecrm\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for SuiteCRM Case entities.
 */
class SuiteCrmCaseForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $result = parent::save($form, $form_state);

    // Get a meaningful label for the message.
    $label = $entity->label();
    if (!$label && $entity->hasField('field_name') && !$entity->get('field_name')->isEmpty()) {
      $label = $entity->get('field_name')->value;
    }
    if (!$label && $entity->hasField('title') && !$entity->get('title')->isEmpty()) {
      $label = $entity->get('title')->value;
    }
    if (!$label) {
      $label = $this->t('the case');
    }

    $message_args = ['%label' => $label];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created new case %label.', $message_args));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated case %label.', $message_args));
    }

    // Redirect to the collection page or home.
    try {
      $form_state->setRedirect('entity.suitecrm_case.collection');
    }
    catch (\Exception $e) {
      // If collection route doesn't exist, redirect to home.
      $form_state->setRedirect('<front>');
    }

    return $result;
  }

}
