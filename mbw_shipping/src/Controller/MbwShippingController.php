<?php

namespace Drupal\mbw_shipping\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\mbw_address\Entity\Address;
use Drupal\mbw_order\OrderService;
use Drupal\mbw_shiptrack\ShipTrack;
use Drupal\mbw_payment\MonerisService;

/**
 * Returns responses for Mbw shipping routes.
 */
class MbwShippingController extends ControllerBase
{

  /**
   * The logger channel factory.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * MBW Order service.
   *
   * @var OrderService
   */
  protected OrderService $order_service;

  /**
   * MBW ShipTrack service.
   *
   * @var ShipTrack
   */
  protected ShipTrack $ship_track;

  /**
   * MBW Payment Moneris service.
   *
   * @var MonerisService
   */
  protected MonerisService $moneris_service;

  /**
   * @var array
   */
  protected array $valid_status;

  /**
   * The controller constructor.
   *
   * @param LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param OrderService $orderService
   *   MBW Order service.
   * @param MonerisService $monerisService
   *   MBW Payment Moneris service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, OrderService $orderService, ShipTrack $shipTrack, MonerisService $monerisService) {
    $this->logger = $logger;
    $this->order_service = $orderService;
    $this->ship_track = $shipTrack;
    $this->moneris_service = $monerisService;

    // Build array of valid statuses by transaction stage
    $this->valid_status = [
      'review' => [
        'order_created',
        'pending_payment',
        'collect_billing',
      ],
      'edit' => [
        'order_created',
        'collect_billing',
      ],
      'payment' => [
        'order_created',
        'pending_payment',
        'preauth_failed',
      ],
      'complete' => [
        'capture_success',
        'capture_failed',
        'refund_success',
      ],
      'success' => [
        'preauth_success',
        'collect_billing',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('mbw_order.order_service'),
      $container->get('mbw_shiptrack.shiptrack'),
      $container->get('mbw_payment.moneris_service')
    );
  }

  /**
   * Generate payment page.
   *
   * @param $order_uuid
   * @return array|RedirectResponse
   */
  public function payment($order_uuid = NULL): RedirectResponse|array
  {
    // Load Order entity.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    if ($order->getOwnerId() != $user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Get order status from entity field.
    $order_status = $order->field_status->value;

    // Check for valid order status.
    if (!$this->order_service->checkOrderStatus($order_uuid, $order_status, $this->valid_status['payment'])) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    // Return payment template.
    // TODO: Lookup payment environment for proper library
    return [
      '#theme' => 'order_payment',
      '#order_uuid' => $order_uuid,
      '#order_id' => $order->id(),
      '#attached' => [
        'library' => [
          'mbw_payment/moneris_testing',
          'mbw_payment/mbw_payment',
          'mbw_shipping/mbw_shipping',
        ],
      ],
    ];
  }

  /**
   * Generate order edit route.
   *
   * @param $order_uuid
   * @return array|RedirectResponse
   */
  public function edit($order_uuid = NULL): array|RedirectResponse
  {
    // Load Order entity.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    if ($order->getOwnerId() != $user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Get order status from entity field.
    $order_status = $order->field_status->value;
    $order_drupal_id = $order->id();

    // Check for valid order status.
    if (!$this->order_service->checkOrderStatus($order_uuid, $order_status, $this->valid_status['edit'])) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $order = \Drupal::entityTypeManager()->getStorage('order')->load($order_drupal_id);
    $form = \Drupal::service('entity.form_builder')->getForm($order, 'edit');

    return $form;
  }

  /**
   * Generate order review route.
   *
   * @param $order_uuid
   * @return array|RedirectResponse
   */
  public function review($order_uuid = NULL): array|RedirectResponse
  {
    // Load Order entity.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    if ($order->getOwnerId() != $user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Get order status from entity field.
    $order_status = $order->field_status->value;

    // Check for valid order status.
    if (!$this->order_service->checkOrderStatus($order_uuid, $order_status, $this->valid_status['review'])) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $order_array = [];
    $order_array['id'] = $order_uuid;
    $order_array['status'] = $order_status;
    $order_array['email'] = $order->get('field_user_email')->value;
    $order_array['requested_date'] = $order->get('requested_date')->value;
    $order_array['from'] = $order->get('field_pickup_address')[0]->organization;
    $order_array['to'] = $order->get('field_destination_address')[0]->organization;
    $order_array['subtotal'] = $order->get('field_cost_subtotal')->value;
    $order_array['fuel_surchage'] = $order->get('field_fuel_surcharge')->value;
    $order_array['tax'] = $order->get('field_cost_tax')->value;
    $order_array['total_cost'] = $order->get('field_cost_total')->value;
    $order_array['show_cost'] = true;
    $order_array['shipping_type'] = $order->get('field_shipping_type')->value;
    $order_array['is_logged_in'] = (bool) $user_id;

    // Return order complete template.
    return [
      '#theme' => 'order_review',
      '#order' => $order_array,
      '#attached' => [
        'library' => [
          'mbw_shipping/mbw_shipping',
        ],
      ],
    ];
  }

  /**
   * Generate order process route.
   *
   * @param null $order_uuid
   *
   * @return RedirectResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function process($order_uuid = NULL): RedirectResponse
  {
    // Load Order entity.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    if ($order->getOwnerId() != $user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Get order status from entity field.
    $order_status = $order->field_status->value;

    // Save address to address book (Destination)
    if ($order->get('field_save_address_pickup')->value) {
      $address = Address::create([
        'uid'                 => $order->getOwnerId(),
        'field_address_type'  => 'pickup',
        'address'             => [
          'country_code'        => $order->get('field_pickup_address')->country_code,
          'organization'        => $order->get('field_pickup_address')->organization,
          'address_line1'       => $order->get('field_pickup_address')->address_line1,
          'address_line2'       => $order->get('field_pickup_address')->address_line2,
          'locality'            => $order->get('field_pickup_address')->locality,
          'administrative_area' => $order->get('field_pickup_address')->administrative_area,
          'postal_code'         => $order->get('field_pickup_address')->postal_code,
        ],
        'field_email'         => $order->get('field_user_email')->value,
        'field_telephone'     => $order->get('field_user_phone')->value,
      ]);
      $address->save();
    }

    // Save address to address book (Destination)
    if ($order->get('field_save_address_destination')->value) {
      $address = Address::create([
        'uid'                 => $order->getOwnerId(),
        'field_address_type'  => 'destination',
        'address'             => [
          'country_code'        => $order->get('field_destination_address')->country_code,
          'organization'        => $order->get('field_destination_address')->organization,
          'address_line1'       => $order->get('field_destination_address')->address_line1,
          'address_line2'       => $order->get('field_destination_address')->address_line2,
          'locality'            => $order->get('field_destination_address')->locality,
          'administrative_area' => $order->get('field_destination_address')->administrative_area,
          'postal_code'         => $order->get('field_destination_address')->postal_code,
        ],
        'field_email'         => $order->get('field_destination_email')->value,
        'field_telephone'     => $order->get('field_destination_phone')->value,
      ]);
      $address->save();
    }

    // Check for valid order status.
    if (!$this->order_service->checkOrderStatus($order_uuid, $order_status, $this->valid_status['success'])) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    // Send to ShipTrack API
    $shiptrack_result = $this->ship_track->createShipment($order_uuid);
    if ($shiptrack_result['status'] === 'success') {
      $order->field_status = 'shipping_label_success';
      $order->field_shiptrack_label_id = $shiptrack_result['label_id'];
      $order->save();

      // Process Payment
      $capture = $this->moneris_service->capturePayment($order_uuid);

      if ($capture) {
        $order->field_status = 'capture_success';
        $order->save();
        return $this->redirect('mbw_shipping.complete', ['order_uuid' => $order_uuid]);
      } else {
        $order->field_status = 'capture_failed';
        $order->save();
        return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
      }
    } else {
      $order->field_status = 'shipping_label_failed';
      $order->save();

      // Refund Payment
      $capture = $this->moneris_service->capturePayment($order_uuid, FALSE);

      $order->field_status = $capture ? 'refund_success' : 'capture_failed';
      $order->save();

      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }
  }

  /**
   * Generate order complete page.
   *
   * @param $order_uuid
   * @return array|RedirectResponse
   */
  public function complete($order_uuid = NULL): RedirectResponse|array
  {
    // Load Order entity.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    if ($order->getOwnerId() != $user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Get order status from entity field.
    $order_status = $order->get('field_status')->value;

    // Check for valid order status.
    if (!$this->order_service->checkOrderStatus($order_uuid, $order_status, $this->valid_status['complete'])) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    // TODO: Get shipping label and payment receipt from APIs.
    $order_total_cost = $order->get('field_cost_total')->value;
    $order_payment_order_no = $order->get('field_payment_order_no')->value;
    $order_shiptrack_label_id = $order->get('field_shiptrack_label_id')->value;

    $order_from = $order->get('field_pickup_address')[0]->organization;
    $order_to = $order->get('field_destination_address')[0]->organization;

    // Return order complete template.
    return [
      '#theme' => 'order_complete',
      '#order_uuid' => $order_uuid,
      '#order_id' => $order->id(),
      '#order_total_cost' => $order->get('field_shipping_type')->value === 'prepaid' ? $order_total_cost : null,
      '#order_payment_order_no' => $order_payment_order_no,
      '#order_shiptrack_label_id' => $order_shiptrack_label_id,
      '#order_from' => $order_from,
      '#order_to' => $order_to,
      '#attached' => [
        'library' => [
          'mbw_shipping/mbw_shipping',
        ],
      ],
    ];
  }

  /**
   * Return generic error page.
   *
   * @param $order_uuid
   * @return array
   */
  public function error($order_uuid = NULL): array
  {
    return [
      '#theme' => 'order_error',
      '#order_uuid' => $order_uuid,
    ];
  }

  /**
   * Return guest login page.
   *
   * @return array
   */
  public function guest(): array
  {
    return [
      '#theme' => 'order_guest',
      '#attached' => [
        'library' => [
          'mbw_shipping/mbw_shipping',
        ],
      ],
    ];
  }

  /**
   * Return guest login page.
   *
   * @return RedirectResponse
   */
  public function continueAsGuest(): RedirectResponse {
    $session = \Drupal::request()->getSession();
    $session->set('mbw_order.guest_access', true);

    return $this->redirect('entity.order.add_form');
  }
}
