<?php

namespace Drupal\mbw_item\Form;

use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Defines the inline form for order items.
 */
class OrderItemInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles) {
    $fields = parent::getTableFields($bundles);

    unset($fields['label']);

    $fields['field_description'] = [
      'type'     => 'callback',
      'callback' => 'Drupal\mbw_item\Form\OrderItemInlineForm::getItemDescription',
      'label'    => $this->t('Description'),
      'weight'   => 1,
    ];

    $fields['field_quantity'] = [
      'type'     => 'callback',
      'callback' => 'Drupal\mbw_item\Form\OrderItemInlineForm::getItemQuantity',
      'label'    => $this->t('Quantity'),
      'weight'   => 2,
    ];

    $fields['field_weight'] = [
      'type'     => 'callback',
      'callback' => 'Drupal\mbw_item\Form\OrderItemInlineForm::getItemWeight',
      'label'    => $this->t('Weight'),
      'weight'   => 3,
    ];

    $fields['field_length'] = [
      'type'     => 'callback',
      'callback' => 'Drupal\mbw_item\Form\OrderItemInlineForm::getItemLength',
      'label'    => $this->t('Length'),
      'weight'   => 4,
    ];

    $fields['field_width'] = [
      'type'     => 'callback',
      'callback' => 'Drupal\mbw_item\Form\OrderItemInlineForm::getItemWidth',
      'label'    => $this->t('Width'),
      'weight'   => 5,
    ];

    $fields['field_height'] = [
      'type'     => 'callback',
      'callback' => 'Drupal\mbw_item\Form\OrderItemInlineForm::getItemHeight',
      'label'    => $this->t('Height'),
      'weight'   => 6,
    ];

    return $fields;
  }

  public static function getItemDescription($entity, $variables) {
    // If the purchased entity couldn't be loaded or doesn't have a SKU...
    if ($entity->get('field_description')->isEmpty()) {
      return 'None';
    }
    else {
      // Otherwise, return the SKU itself.
      return $entity->get('field_description')->value;
    }
  }

  public static function getItemQuantity($entity, $variables) {
    // If the purchased entity couldn't be loaded or doesn't have a SKU...
    if (!$entity->get('field_quantity')->isEmpty()) {
      // Return a placeholder string.
      return $entity->get('field_quantity')->value;
    }
    else {
      return '';
    }
  }

  public static function getItemWeight($entity, $variables) {
    // If the purchased entity couldn't be loaded or doesn't have a SKU...
    if (!$entity->get('field_weight')->isEmpty()) {
      // Return a placeholder string.
      $weight = $entity->get('field_weight')->value;

      return sprintf('%s lbs', $weight);
    }
    else {
      return '';
    }
  }

  public static function getItemLength($entity, $variables) {
    // If the purchased entity couldn't be loaded or doesn't have a SKU...
    if (!$entity->get('field_length')->isEmpty()) {
      // Return a placeholder string.
      $length = $entity->get('field_length')->value;

      return sprintf('%s in', $length);
    }
    else {
      return '';
    }
  }

  public static function getItemWidth($entity, $variables) {
    // If the purchased entity couldn't be loaded or doesn't have a SKU...
    if (!$entity->get('field_width')->isEmpty()) {
      // Return a placeholder string.
      $width = $entity->get('field_width')->value;

      return sprintf('%s in', $width);
    }
    else {
      return '';
    }
  }

  public static function getItemHeight($entity, $variables) {
    // If the purchased entity couldn't be loaded or doesn't have a SKU...
    if (!$entity->get('field_height')->isEmpty()) {
      // Return a placeholder string.
      $height = $entity->get('field_height')->value;

      return sprintf('%s in', $height);
    }
    else {
      return '';
    }
  }

}
