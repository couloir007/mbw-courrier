<?php

namespace Drupal\mbw_order;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\mbw_order\Entity\Order;

/**
 * Service description.
 */
class OrderService {

  /**
   * The logger channel factory.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * The config factory object.
   *
   * @var ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config_factory;

  /**
   * Config object.
   *
   * @var ImmutableConfig
   */
  protected ImmutableConfig $shipping_config;

  /**
   * Constructs an OrderService object.
   *
   * @param LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, ConfigFactoryInterface $configFactory,) {
    $this->logger = $logger;
    $this->config_factory = $configFactory;

    $this->shipping_config = $this->config_factory->get('mbw_shipping.settings');
  }

  /**
   * Load Order entity by UUID.
   *
   * @param $uuid
   * @return EntityInterface|false
   */
  public function getOrderByUuid($uuid): bool|EntityInterface
  {
    // Attempt to load entity.
    try {
      $entities = \Drupal::entityTypeManager()->getStorage('order')->loadByProperties(['uuid' => $uuid]);
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      $this->logger->get('mbw_order')->error('Could not load order with uuid %uuid', ['%uuid' => $uuid]);
      return FALSE;
    }

    // Check for at least one returned Order entity.
    if (!count($entities)) {
      $this->logger->get('mbw_order')->error('Could not load order with uuid %uuid', ['%uuid' => $uuid]);
      return FALSE;
    }

    // Return one (first) entity.
    return reset($entities);
  }

  /**
   * Check order status against expected status.
   *
   * @param string $order_uuid
   * @param string $order_status
   * @param array $valid_status
   * @return bool
   */
  public function checkOrderStatus(string $order_uuid, string $order_status, array $valid_status): bool
  {
    // Check status against valid array.
    if (!in_array($order_status, $valid_status)) {
      $this->logger->get('mbw_order')->error('Order %order_id has incorrect status: %order_status', [
        '%order_id' => $order_uuid,
        '%order_status' => $order_status,
      ]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get itemized list of order items.
   *
   * @param Order $order
   * @return array
   */
  public function getOrderLineItems(Order $order): array {
    $order_items = $order->get('field_items')->referencedEntities();
    $items = [];

    // Loop through order items.
    foreach ($order_items as $order_item) {
      $item_desc = $order_item->get('field_description')->value;
      $item_quantity = $order_item->get('field_quantity')->value;
      $item_weight = $order_item->get('field_weight')->value;
      $item_length = $order_item->get('field_length')->value;
      $item_width = $order_item->get('field_width')->value;
      $item_height = $order_item->get('field_height')->value;

      $items[] = [
        'description' => $item_desc ?? sprintf('Piece: %s x %s x %s', $item_length, $item_width, $item_height),
        'unit_cost' => $this->formatPrice($order_item->get('field_cost')->value),
        'quantity' => $item_quantity,
        'weight' => $item_weight,
        'length' => $item_length,
        'width' => $item_width,
        'height' => $item_height,
      ];
    }

    return $items;
  }

  /**
   * Format price.
   *
   * @param float $price
   * @return string
   */
  public function formatPrice(float $price): string
  {
    return number_format($price, 2, '.', ',');
  }

  /**
   * Calculate subtotal cost of order.
   *
   * @param Order $order
   *
   * @return float
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function calculateOrderSubTotal(Order $order): float
  {
    $subtotal = 0;
    $override = [];

    // Get order items.
    $order_items = $order->get('field_items')->referencedEntities();

    // Look for client discount
    $order_user = $order->get('uid')->entity->uid->value;
    if ($order_user) {
      $override = $this->getClientOverride($order_user);
    }

    // Loop through order items.
    foreach ($order_items as $order_item) {
      $weight = $order_item->get('field_weight')->value;
      $item_cost = $order_item->get('field_cost')->value;

      // Only loop when client specific pricing is found
      if ($override) {
        switch (TRUE) {
          case $weight <= 10 && $override['0_10']:
            $item_cost = $override['0_10'];
            break;
          case $weight <= 25 && $override['11_25']:
            $item_cost = $override['11_25'];
            break;
          case $weight <= 50 && $override['26_50']:
            $item_cost = $override['26_50'];
            break;
          case $weight <= 75 && $override['51_75']:
            $item_cost = $override['51_75'];
            break;
          case $weight <= 100 && $override['76_100']:
            $item_cost = $override['76_100'];
            break;
          case $weight <= 125 && $override['101_125']:
            $item_cost = $override['101_125'];
            break;
          case $weight <= 150 && $override['126_150']:
            $item_cost = $override['126_150'];
            break;
          case $weight <= 175 && $override['151_175']:
            $item_cost = $override['151_175'];
            break;
          case $weight <= 200 && $override['176_200']:
            $item_cost = $override['176_200'];
            break;
          case $weight <= 500 && $override['201_500'] && $override['176_200']:
            $cost      = $override['176_200'];
            $extra     = ($weight - 200) * $override['201_500'];
            $item_cost = $cost + $extra;
            break;
          case $weight <= 1000 && $override['201_500'] && $override['176_200'] && $override['501_1000']:
            $cost          = $override['176_200'];
            $extra         = 300 * $override['201_500'];
            $extra_phase_2 = ($weight - 500) * $override['501_1000'];
            $item_cost     = $cost + $extra + $extra_phase_2;
            break;
          default:
            if ($override['201_500'] && $override['176_200'] && $override['501_1000'] && $override['1001_plus']) {
              $cost          = $override['176_200'];
              $extra         = 300 * $override['201_500'];
              $extra_phase_2 = 500 * $override['501_1000'];
              $extra_phase_3 = ($weight - 1000) * $override['1001_plus'];
              $item_cost     = $cost + $extra + $extra_phase_2 + $extra_phase_3;
            }
            break;
        }
      }

      $subtotal += $item_cost;
    }

    return round($subtotal, 2);
  }

  /**
   * Calculate fuel surcharge on order.
   *
   * @param float $subtotal
   * @param \Drupal\mbw_order\Entity\Order $order
   *
   * @return float
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function calculateFuelSurcharge(float $subtotal, Order $order): float
  {
    // Get fuel surcharge.
    $fuel_surcharge = $this->shipping_config->get('fuel_surcharge');

    // Check for Client Override
    $order_user = $order->get('uid')->entity->uid->value;
    if ($order_user) {
      $override = $this->getClientOverride($order_user);

      if ($override && $override['fuel_surcharge'] > 0) {
        $fuel_surcharge = $override['fuel_surcharge'];
      }
    }

    return $fuel_surcharge ? round($subtotal * ($fuel_surcharge / 100), 2) : 0;
  }

  /**
   * Calculate tax on order.
   *
   * @param float $subtotal
   * @param float $fuel_surcharge
   * @return float
   */
  public function calculateOrderTax(float $subtotal, float $fuel_surcharge): float
  {
    // Get order total cost.
    $tax_rate = $this->shipping_config->get('tax_rate');

    return $tax_rate ? round(($subtotal + $fuel_surcharge) * ($tax_rate / 100), 2) : 0;
  }

  /**
   * Calculate tax on order.
   *
   * @param float $subtotal
   * @param float $fuel_surcharge
   * @param float $tax
   * @return float
   */
  public function calculateOrderTotal(float $subtotal, float $fuel_surcharge, float $tax): float
  {
    return $subtotal + $fuel_surcharge + $tax;
  }

  /**
   * @param int $user_id
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getClientOverride(int $user_id): array {
    $override = [];

    // Query client_discounts for entries with user ID
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'client_discount')
      ->condition('field_user', $user_id)
      ->range(0,1);
    $query->accessCheck(FALSE);
    $results = $query->execute();

    // Apply discount based on queried node
    if (count($results)) {
      $discount_node_id = reset($results);
      $discount_node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($discount_node_id);
      $fuel_surcharge = $discount_node->get('field_fuel_surcharge')->value;
      $rate_0 = $discount_node->get('field_0_10_lbs')->value;
      $rate_1 = $discount_node->get('field_11_25_lbs')->value;
      $rate_2 = $discount_node->get('field_25_50_lbs')->value; // Mistake in field_in not label
      $rate_3 = $discount_node->get('field_51_75_lbs')->value;
      $rate_4 = $discount_node->get('field_76_100_lbs')->value;
      $rate_5 = $discount_node->get('field_101_125_lbs')->value;
      $rate_6 = $discount_node->get('field_126_150_lbs')->value;
      $rate_7 = $discount_node->get('field_151_175_lbs')->value;
      $rate_8 = $discount_node->get('field_176_200_lbs')->value;
      $rate_9 = $discount_node->get('field_201_500_lbs')->value;
      $rate_10 = $discount_node->get('field_501_1000_lbs')->value;
      $rate_11 = $discount_node->get('field_1001_lbs')->value;

      $override = [
        'fuel_surcharge' => $fuel_surcharge,
        '0_10' => $rate_0,
        '11_25' => $rate_1,
        '26_50' => $rate_2,
        '51_75' => $rate_3,
        '76_100' => $rate_4,
        '101_125' => $rate_5,
        '126_150' => $rate_6,
        '151_175' => $rate_7,
        '176_200' => $rate_8,
        '201_500' => $rate_9,
        '501_1000' => $rate_10,
        '1001_plus' => $rate_11,
      ];
    }

    return $override;
  }
}
