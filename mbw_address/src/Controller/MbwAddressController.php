<?php

namespace Drupal\mbw_address\Controller;

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
class MbwAddressController extends ControllerBase {

  /**
   * The logger channel factory.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

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
  public function __construct(LoggerChannelFactoryInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
    );
  }

  /**
   * Generate manage address page.
   */
  public function manage(): array {
    $user      = \Drupal::currentUser();
    $addresses = [];

    $query   = \Drupal::entityQuery('address')
                      ->condition('uid', $user->id());
    $results = $query->execute();

    if (count($results)) {
      foreach ($results as $address_id) {
        $address = \Drupal::entityTypeManager()
                          ->getStorage('address')
                          ->load($address_id);

        $allowed_values = $address->getFieldDefinition('field_address_type')->getFieldStorageDefinition()->getSetting('allowed_values');
        $addresses[] = [
          'id'            => $address_id,
          'type'          => $allowed_values[$address->get('field_address_type')->value],
          'email'         => $address->get('field_email')->value,
          'phone'         => $address->get('field_telephone')->value,
          'organization'  => $address->get('address')->organization,
          'address_line1' => $address->get('address')->address_line1,
          'locality'      => $address->get('address')->locality,
        ];
      }
    }

    // Return payment template.
    return [
      '#theme'     => 'address_manage',
      '#addresses' => $addresses,
    ];
  }

  /**
   *
   */
  public function delete(int $addressId) {
    $user = \Drupal::currentUser();

    $address = \Drupal::entityTypeManager()
                      ->getStorage('address')
                      ->load($addressId);

    if ($user->id() == $address->getOwnerId()) {
      $address->delete();
    }

    return $this->redirect('mbw_address.manage');
  }
}
