<?php

namespace Drupal\mbw_address\Form;

use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Defines the inline form for addresses.
 */
class AddressInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles) {
    $fields = parent::getTableFields($bundles);

    unset($fields['label']);

    $fields['address'] = [
        'type' => 'callback',
        'callback' => 'Drupal\mbw_address\Form\AddressInlineForm::getAddressDescription',
        'label' => $this->t('Address'),
        'weight' => 1,
    ];

    return $fields;
  }

  /**
   * Returns the SKU for the product referenced by an order item.
   */
  public static function getAddressDescription($entity, $variables) {
    $address = $entity->get('address')->address_line1;
    $province = $entity->get('address')->administrative_area;

    return $address . ', ' . $province;
  }
}
