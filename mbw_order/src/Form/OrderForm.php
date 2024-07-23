<?php

namespace Drupal\mbw_order\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the order entity edit forms.
 */
class OrderForm extends ContentEntityForm {

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $result = parent::validateForm($form, $form_state);

    // Load config
    $config = \Drupal::config('mbw_shipping.settings');
    $max_weight = $config->get('max_order_weight') ?? 2200;

    // Calculate maximum weight
    $current_weight = 0;
    foreach($result->get('field_items')->getValue() as $item) {
      if (!empty($item['entity'])) {
        $item_entity = $item['entity'];
      } else {
        $item_entity = \Drupal::entityTypeManager()
                              ->getStorage('item')
                              ->load($item['target_id']);
      }
      $item_quantity = $item_entity->get('field_quantity')->value;
      $item_weight = $item_entity->get('field_weight')->value * $item_quantity;
      $current_weight += $item_weight;

      if ($current_weight > $max_weight) {
        $form_state->setErrorByName('field_items', $this->t("Maximum combined weight is {$max_weight} lbs."));
      }
    }
    // Ensure Request Date is in the future
    if (intval($form_state->getValue('requested_date')[0]['value']->format('ymd')) < intval(date('ymd'))) {
      $form_state->setErrorByName('field_requested_date', $this->t('Please chose a date in the future.'));
    }

    // Load Account IDs from settings
    $account_ids = $config->get('account_ids');
    $account_array = explode(',', $account_ids);

    // Check account ID against valid array of IDs
    $requires_account = [
      'collect',
      'third_party',
    ];

//    if (in_array($form_state->getValue('field_shipping_type')[0]['value'], $requires_account) && !in_array($form_state->getValue('field_account_number')[0]['value'], $account_array)) {
//      $form_state->setErrorByName('field_account_number', $this->t('Please enter a valid account number.'));
//    }

    // Check for Postal Codes
    $postal_codes = $config->get('excluded_postal_codes');
    $code_array = explode(',', $postal_codes);

    $form_postal_code = str_replace(' ', '', $form_state->getValue('field_destination_address')[0]['address']['postal_code']);
    if (in_array($form_postal_code, $code_array)) {
      $form_state->setErrorByName('field_destination_address', $this->t('Shipping is not available to this postal code.'));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();
    $order_service = \Drupal::service('mbw_order.order_service');
    $shipping_settings = \Drupal::config('mbw_shipping.settings');

    // Loop through order items to assign cost.
    $order_items = $entity->get('field_items')->referencedEntities();
    foreach($order_items as $order_item) {
      $quantity = $order_item->get('field_quantity')->value;
      $weight = $order_item->get('field_weight')->value * $quantity;

      // So UGLY
      switch (true) {
        case $weight <= 10:
          $order_item->field_cost = $shipping_settings->get('rates_tier_1');
          break;
        case $weight <= 25:
          $order_item->field_cost = $shipping_settings->get('rates_tier_2');
          break;
        case $weight <= 50:
          $order_item->field_cost = $shipping_settings->get('rates_tier_3');
          break;
        case $weight <= 75:
          $order_item->field_cost = $shipping_settings->get('rates_tier_4');
          break;
        case $weight <= 100:
          $order_item->field_cost = $shipping_settings->get('rates_tier_5');
          break;
        case $weight <= 125:
          $order_item->field_cost = $shipping_settings->get('rates_tier_6');
          break;
        case $weight <= 150:
          $order_item->field_cost = $shipping_settings->get('rates_tier_7');
          break;
        case $weight <= 175:
          $order_item->field_cost = $shipping_settings->get('rates_tier_8');
          break;
        case $weight <= 200:
          $order_item->field_cost = $shipping_settings->get('rates_tier_9');
          break;
        case $weight <= 500:
          $cost = $shipping_settings->get('rates_tier_9');
          $extra = ($weight - 200) * $shipping_settings->get('rates_tier_10');
          $order_item->field_cost = $cost + $extra;
          break;
        case $weight <= 1000:
          $cost = $shipping_settings->get('rates_tier_9');
          $extra = 300 * $shipping_settings->get('rates_tier_10');
          $extra_phase_2 = ($weight - 500) * $shipping_settings->get('rates_tier_11');
          $order_item->field_cost = $cost + $extra + $extra_phase_2;
          break;
        default:
          $cost = $shipping_settings->get('rates_tier_9');
          $extra = 300 * $shipping_settings->get('rates_tier_10');
          $extra_phase_2 = 500 * $shipping_settings->get('rates_tier_11');
          $extra_phase_3 = ($weight - 1000) * $shipping_settings->get('rates_tier_12');
          $order_item->field_cost = $cost + $extra + $extra_phase_2 + $extra_phase_3;
          break;
      }

      $order_item->save();
    }

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    // Set custom status for Collect billing
    if ($entity->get('field_shipping_type')->value === 'collect' || $entity->get('field_shipping_type')->value === 'third_party' || $user_id) {
      $entity->field_status = 'collect_billing';
    } else {
      $entity->field_status = 'order_created';
    }

    // Calculate the order costs.
    $order_subtotal = $order_service->calculateOrderSubTotal($entity);
    $order_fuel_surcharge = $order_service->calculateFuelSurcharge($order_subtotal, $entity);
    $order_tax = $order_service->calculateOrderTax($order_subtotal,  $order_fuel_surcharge);
    $order_total = $order_service->calculateOrderTotal($order_subtotal, $order_fuel_surcharge, $order_tax);

    $entity->field_cost_subtotal = $order_service->formatPrice($order_subtotal);
    $entity->field_fuel_surcharge = $order_service->formatPrice($order_fuel_surcharge);
    $entity->field_cost_tax = $order_service->formatPrice($order_tax);
    $entity->field_cost_total = $order_service->formatPrice($order_total);

    // Create entry in address book
    if ($user_id) {
      $entity->field_save_address_pickup = $form['pickup_address_container']["field_save_address_pickup"]["#checked"];
      $entity->field_save_address_destination = $form['destination_address_container']["field_save_address_destination"]["#checked"];
    }

    // Save changes to entity
    $entity->save();

    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->logger('mbw_order')->notice('Created new order %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->logger('mbw_order')->notice('Updated order %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('mbw_shipping.review', ['order_uuid' => $entity->uuid()]);

    return $result;
  }

}
