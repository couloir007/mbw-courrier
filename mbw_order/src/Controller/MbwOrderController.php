<?php

namespace Drupal\mbw_order\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\mbw_order\OrderService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for MBW Order routes.
 */
class MbwOrderController extends ControllerBase {

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The mbw_order.order_service service.
   *
   * @var \Drupal\mbw_order\OrderService
   */
  protected $orderService;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\mbw_order\OrderService $order_service
   *   The mbw_order.order_service service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, OrderService $order_service) {
    $this->logger = $logger;
    $this->orderService = $order_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('mbw_order.order_service')
    );
  }

  /**
   * Builds the response.
   */
  public function viewByUuid($order_uuid = NULL) {
    // Load Order entity.
    if (!$order = $this->orderService->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    if ($order->getOwnerId() !== $user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Redirect to order view page.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('order');
    $pre_render = $view_builder->view($order, 'full');
    $render_output = \Drupal::service('renderer')->render($pre_render);

    return [
      '#theme' => 'order_detail',
      '#order' => $render_output,
    ];
  }

}
