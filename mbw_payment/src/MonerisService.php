<?php

namespace Drupal\mbw_payment;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\mbw_order\Entity\Order;
use Drupal\mbw_order\OrderService;

/**
 * Service description.
 */
class MonerisService {

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
   * MBW Order service.
   *
   * @var OrderService
   */
  protected OrderService $order_service;

  /**
   * Config object.
   *
   * @var ImmutableConfig
   */
  protected ImmutableConfig $payment_config;

  /**
   * Constructs an OrderService object.
   *
   * @param LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, ConfigFactoryInterface $configFactory, OrderService $orderService)
  {
    $this->logger = $logger;
    $this->config_factory = $configFactory;
    $this->order_service = $orderService;

    require( __DIR__ . '/mpgClasses.php');

    $this->payment_config = $this->config_factory->get('mbw_payment.settings');
  }

  public function capturePayment($order_uuid = '', $result = TRUE)
  {
    // Get order.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return FALSE;
    }

    $order_token = $order->get('field_payment_ticket_id')->value;

    $this->logger->get('mbw_payment')->info('%method payment for %order_id and token %token.', [
      '%method' => $result ? 'Capturing' : 'Refunding',
      '%order_id' => $order_uuid,
      '%token' => $order_token,
    ]);

    // Transaction details.
    $store_id = $this->payment_config->get('store_id');
    $api_token = $this->payment_config->get('api_key');
    $order_id = $order->get('field_payment_order_no')->value;
    $txn_number = $order->get('field_payment_transaction_no')->value;
    $amount = $result ? $order->get('field_cost_total')->value : '0.00';
    $crypt_type = 7;

    $transaction = [
      'type' => 'completion',
      'txn_number' => $txn_number,
      'order_id' => $order_id,
      'comp_amount' => $amount,
      'crypt_type' => $crypt_type,
    ];

    // Send API capture transaction.
    $mpg_transaction = new \mpgTransaction($transaction);

    $mpg_request = new \mpgRequest($mpg_transaction);
    $mpg_request->setProcCountryCode("CA");
    $mpg_request->setTestMode($this->payment_config->get('environment') == 'qa');

    $mpg_http_post  = new \mpgHttpsPost($store_id, $api_token, $mpg_request);

    $mpg_response = $mpg_http_post->getMpgResponse();

    if ($mpg_response->getComplete()) {
      $this->logger->get('mbw_payment')->info('Payment for %order_id and token %token successfully %method.', [
        '%method' => $result ? 'captured' : 'refunded',
        '%order_id' => $order_uuid,
        '%token' => $order_token,
      ]);

      return TRUE;
    } else {
      $this->logger->get('mbw_payment')->error('Payment for %order_id and token %token could not be %method.', [
        '%method' => $result ? 'captured' : 'refunded',
        '%order_id' => $order_uuid,
        '%token' => $order_token,
      ]);

      return FALSE;
    }
  }
}
