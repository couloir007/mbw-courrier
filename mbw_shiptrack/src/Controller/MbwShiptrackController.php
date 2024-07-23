<?php

namespace Drupal\mbw_shiptrack\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\mbw_order\OrderService;
use Drupal\mbw_shiptrack\ShipTrack;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Returns responses for Mbw shipping routes.
 */
class MbwShiptrackController extends ControllerBase
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
   * The controller constructor.
   *
   * @param LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param OrderService $orderService
   *   MBW Order service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, OrderService $orderService, ShipTrack $shipTrack) {
    $this->logger = $logger;
    $this->order_service = $orderService;
    $this->ship_track = $shipTrack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('mbw_order.order_service'),
      $container->get('mbw_shiptrack.shiptrack'),
    );
  }

  public function getLabel($order_uuid = NULL)
  {
    // Load Order entity.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    // Get label ID.
    $label_id = $order->get('field_shiptrack_label_id')->value;

    // Call ShipTrack service to get label.
    $file = FALSE;
    if ($label_id) {
      $file = $this->ship_track->callLabelApi($order_uuid, $label_id);
    }

    if ($file) {
      $this->logger->get('mbw_shiptrack')->info('ShipTrack label API for order: %order_uuid returned successfully.' , ['%order_uuid' => $order_uuid]);

      // Return label if successful.
      $headers = array(
        'Content-Type' => $file->getMimeType(),
        'Content-Disposition' => 'attachment;filename="'.$file->getFilename().'"',
        'Content-Length' => $file->getSize(),
        'Content-Description' => 'Shipping Label',
      );
      
      return new BinaryFileResponse($file->getFileUri(), 200, $headers, true);
    } else {
      return FALSE;
    }
  }
}
