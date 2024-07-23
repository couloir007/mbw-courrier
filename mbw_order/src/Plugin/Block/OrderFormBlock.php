<?php

namespace Drupal\mbw_order\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an order form block.
 *
 * @Block(
 *   id = "mbw_order_order_form",
 *   admin_label = @Translation("Order Form"),
 *   category = @Translation("MBW")
 * )
 */
class OrderFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $order = \Drupal::entityTypeManager()->getStorage('order')->create();
    $form = \Drupal::service('entity.form_builder')->getForm($order, 'add');

    return $form;
  }

}
