<?php

namespace Drupal\mbw_payment\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\mbw_order\OrderService;
use Drupal\mbw_order\Entity\Order;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for MBW Payment routes.
 */
class MbwPaymentController extends ControllerBase
{

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
   * Payment config object.
   *
   * @var ImmutableConfig
   */
  protected ImmutableConfig $payment_config;

  /**
   * Shipping config object.
   *
   * @var ImmutableConfig
   */
  protected ImmutableConfig $shipping_config;

  /**
   * Payment Token/Receipt endpoint.
   *
   * @var string
   */
  protected string $request_endpoint = '';

  /**
   * Payment result code to label mapping.
   *
   * @var array
   */
  protected array $payment_result_mapping = [];

  protected string $order_uuid;

  protected Order $order;

  const ACCEPTED_STRING = 'a';

  /**
   * The controller constructor.
   *
   * @param LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param OrderService $orderService
   *   MBW Order service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, ConfigFactoryInterface $configFactory, OrderService $orderService) {
    $this->logger = $logger;
    $this->config_factory = $configFactory;
    $this->order_service = $orderService;

    $this->payment_config = $this->config_factory->get('mbw_payment.settings');
    $this->shipping_config = $this->config_factory->get('mbw_shipping.settings');

    if ($this->payment_config->get('environment') == "prod") {
      $this->request_endpoint = $this->payment_config->get('request_endpoint_prod');
    } else {
      $this->request_endpoint = $this->payment_config->get('request_endpoint_qa');
    }

    $this->payment_result_mapping = [
      'a' => 'Payment Approved',
      'd' => 'Payment Declined',
    ];

    // Get query params.
    $this->order_uuid = \Drupal::request()->request->get('order_uuid');

    // Load Order entity.
    if (!$this->order = $this->order_service->getOrderByUuid($this->order_uuid)) {
      return new JsonResponse([
        'data' => [
          'status' => 'failure',
          'order_uuid' => $order_uuid,
        ],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('mbw_order.order_service')
    );
  }

  public function preload()
  {
    // Get request parameters.
    $ticket_expired = \Drupal::request()->request->get('ticket_expired');

    // If payment token exists, return.
    $order_token = $this->order->get('field_payment_ticket_id')->value;
    // TODO: Make sure token isn't expired.
    if ($order_token && $ticket_expired != 'true') {
      return new JsonResponse([
        'data' => [
          'status' => 'success',
          'token' => $order_token,
          'order_uuid' => $this->order_uuid,
        ],
      ]);
    }

    // Get order details.
    $order_email = $this->order->get('field_user_email')->value;

    // Get array of order items.
    $order_items = $this->order_service->getOrderLineItems($this->order);

    // Get cost from Order.
    $order_cost_subtotal = $this->order->get('field_cost_subtotal')->value;
    $order_cost_tax = $this->order->get('field_cost_tax')->value;
    $order_cost_total = $this->order->get('field_cost_total')->value;

    // Log preload request.
    $this->logger->get('mbw_payment')->info('Getting payment preload token for order %order_id with a cost of %cost_total.', [
      '%order_id' => $this->order_uuid,
      '%cost_total' => $order_cost_total,
    ]);

    $payload = [
      "store_id" => $this->payment_config->get('store_id'),
      "api_token" => $this->payment_config->get('api_key'),
      "checkout_id" => $this->payment_config->get('checkout_id'),
      "txn_total" => $order_cost_total,
      "environment" => $this->payment_config->get('environment'),
      "action" => "preload",
      "cust_id" => $order_email,
      "cart" => [
        "items" => $order_items,
        "subtotal" => $order_cost_subtotal,
        "tax" => [
          "amount" => $order_cost_tax,
          "description" => $this->shipping_config->get('tax_label') ?? t('Tax'),
          "rate" => $this->shipping_config->get('tax_rate') ?? '0.00',
        ],
      ],
      "contact_details" => [
        "email" => $order_email,
      ],
    ];

    $client = \Drupal::httpClient();
    $request = $client->post($this->request_endpoint, [
      'json' => $payload
    ]);
    $response = json_decode($request->getBody());
    $response_status = $response->response->success;
    $response_ticket = $response->response->ticket;

    if ($response_status == "true") {
      $this->logger->get('mbw_payment')->info('Payment preload token for %order_id acquired: %token', [
        '%order_id' => $this->order_uuid,
        '%token' => $response_ticket,
      ]);

      $this->order->field_payment_ticket_id = $response_ticket;
      $this->order->field_status = 'pending_payment';

      $this->order->save();

      return new JsonResponse([
        'data' => [
          'status' => 'success',
          'token' => $response->response->ticket,
          'order_uuid' => $this->order_uuid,
        ],
      ]);
    } else {
      $this->logger->get('mbw_payment')->error('Payment preload token for %order_id could not be acquired.', [
        '%order_id' => $this->order_uuid,
      ]);

      return new JsonResponse([
        'data' => [
          'status' => 'failure',
          'order_uuid' => $this->order_uuid,
        ],
      ]);
    }
  }

  public function preauth()
  {
    // Get cost from Order
    $order_ticket = $this->order->get('field_payment_ticket_id')->value;

    // Log request
    $this->logger->get('mbw_payment')->info('Getting preauth receipt for order %order_id with ticket %ticket.', [
      '%order_id' => $this->order_uuid,
      '%ticket' => $order_ticket,
    ]);

    $client = \Drupal::httpClient();
    $request = $client->post($this->request_endpoint, [
      'json' => [
        "store_id" => $this->payment_config->get('store_id'),
        "api_token" => $this->payment_config->get('api_key'),
        "checkout_id" => $this->payment_config->get('checkout_id'),
        "ticket" => $order_ticket,
        "environment" => $this->payment_config->get('environment'),
        "action" => "receipt",
      ]
    ]);
    $response = json_decode($request->getBody());

    if ($response->response->success == "true") {
      $this->logger->get('mbw_payment')->info('Preauth receipt for %order_id acquired: %token', [
        '%order_id' => $this->order_uuid,
        '%token' => $order_ticket,
      ]);

      // Get preauth results.
      $receipt = $response->response->receipt;
      $receipt_result = $this->payment_result_mapping[$receipt->result];

      // Map result code to friendly status.
      $this->order->field_status = $receipt->result === self::ACCEPTED_STRING ? 'preauth_success' : 'preauth_failed';

      // If preauth was successful, save details.
      if ($receipt->result === self::ACCEPTED_STRING) {
        $this->order->field_payment_order_no = $receipt->cc->order_no;
        $this->order->field_payment_transaction_no = $receipt->cc->transaction_no;
      }

      // Save order.
      $this->order->save();

      // Return results.
      return new JsonResponse([
        'data' => [
          'status' => 'success',
          'result' => $receipt_result,
          'order_uuid' => $this->order_uuid,
        ],
      ]);
    } else {
      $this->logger->get('mbw_payment')->error('Preauth receipt for %order_id and token %token could not be acquired.', [
        '%order_id' => $this->order_uuid,
        '%token' => $response->response->ticket,
      ]);

      return new JsonResponse([
        'data' => [
          'status' => 'failure',
          'order_uuid' => $this->order_uuid,
        ],
      ]);
    }
  }
}
