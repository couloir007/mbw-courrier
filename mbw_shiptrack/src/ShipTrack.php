<?php

namespace Drupal\mbw_shiptrack;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\mbw_order\OrderService;
use GuzzleHttp\ClientInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;

/**
 * Service description.
 */
class ShipTrack {

  /**
   * The logger channel factory.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var ClientInterface
   */
  protected $httpClient;

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
  protected ImmutableConfig $shiptrack_config;

  /**
   * MBW Order service.
   *
   * @var OrderService
   */
  protected OrderService $order_service;

  /**
   * Current Date for Shipment.
   */
  protected string $current_date;

  /**
   * Constructs a ShipTrack object.
   *
   * @param LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param ClientInterface $http_client
   *   The HTTP client to fetch the feed data with.
   * @param ConfigFactoryInterface $config_factory
   *   The config factory object.
   * @param OrderService $orderService
   *   MBW Order service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, ClientInterface $httpClient, ConfigFactoryInterface $configFactory, OrderService $orderService) {
    $this->logger = $logger;
    $this->http_client = $httpClient;
    $this->config_factory = $configFactory;
    $this->order_service = $orderService;

    // Get config.
    $this->shiptrack_config = $this->config_factory->get('mbw_shiptrack.settings');

    // Set current date.
    $date = new \DateTime();
    $this->current_date = $date->format('c');
  }

  /**
   * Method description.
   */
  public function createShipment($order_uuid = NULL) {
    $this->logger->get('mbw_shiptrack')->info('Creating shipment for order: %order_uuid', ['%order_uuid' => $order_uuid]);

    // Load Order entity.
    if (!$order = $this->order_service->getOrderByUuid($order_uuid)) {
      return $this->redirect('mbw_shipping.error', ['order_uuid' => $order_uuid]);
    }

    // Order helpers.
    $client_id = "MBW";
    $sales_order = $client_id . ($order->id() + 2200); // Min 1001.

    // Transform service type to code.
    $service_types = [
      'prepaid' => 'PREPAID',
      'collect' => 'COLLECT',
      'third_party' => '3RDPARTY',
    ];

    $account_number = 'O0067';
    // Load Drupal user and get ID, pass into AccountNumber if service type is "prepaid"
    $user_id = \Drupal::currentUser()->id();
    $user = User::load($user_id);
    if ($user_id && $user && $order->get('field_shipping_type')->value == 'prepaid') {
      $account_number = $user->get('field_account_number')->value;
    }

    // Get general details from Order.
    $details = [
      "Client" => [
        "EDIClientID" => $account_number,
      ],
      "RequestedDate" => $order->get('requested_date')->value,
      "ServiceType" => [
        "ServiceCode" => $service_types[$order->get('field_shipping_type')->value],
        "ServiceTypeOptions" => [],
      ],
      "JobOption" => null,
      "PaymentTypeCode" => '',
      "AccountNumber" => null,
      "DeclaredValue" => null,
      "Description" => null,
      "ParcelType" => null,
      "SalesOrder" => null,
      "CarrierRef" => $sales_order,
      "Reference1" => null, // Blank unless we want to use it.
      "Reference2" => $order->get('field_account_number')->value ?? null, // Account number
      "Comments" => $order->get('field_comments')->value ? strip_tags($order->get('field_comments')->value) : null,
    ];

    // Get Pickup Address from Order.
    $pickup_address_field = $order->get('field_pickup_address');
    $pickup_address = [
      "RouteCode" => "",
      "CompanyName" => $pickup_address_field->organization,
      "Address1" => $pickup_address_field->address_line1,
      "Address2" => $pickup_address_field->address_line2,
      "Address3" => "",
      "City" => $pickup_address_field->locality,
      "ProvinceStateCode" => $pickup_address_field->administrative_area,
      "PostalZipCode" => $pickup_address_field->postal_code,
      "CountryCode" => $pickup_address_field->country_code,
      "PhoneNumber" => $order->get('field_user_phone')->value,
      "Email" => $order->get('field_user_email')->value,
      "TransitNotification" => true, // TODO: Pull from order once added.
      "PODNotification" => true, // TODO: Pull from order once added.
    ];

    // Get Destination Address from Order.
    $destination_address_field = $order->get('field_destination_address');
    $destination_address = [
      "RouteCode" => "",
      "CompanyName" => $destination_address_field->organization,
      "Address1" => $destination_address_field->address_line1,
      "Address2" => $destination_address_field->address_line2,
      "Address3" => "",
      "City" => $destination_address_field->locality,
      "ProvinceStateCode" => $destination_address_field->administrative_area,
      "PostalZipCode" => $destination_address_field->postal_code,
      "CountryCode" => $destination_address_field->country_code,
      "PhoneNumber" => $order->get('field_destination_phone')->value,
      "Email" => "", $order->get('field_destination_email')->value,
      "TransitNotification" => true, // TODO: Pull from order once added.
      "PODNotification" => true, // TODO: Pull from order once added.
    ];

    // Get Items from Order.
    $order_items = $this->order_service->getOrderLineItems($order);
    $items = [];
    foreach($order_items as $order_item) {
      $item = [
        "Description" => $order_item['description'],
        "Weight" => $order_item['weight'],
        "Length" => $order_item['length'],
        "Width" => $order_item['width'],
        "Height" => $order_item['height'],
        "Barcode" => null,
      ];

      for ($i = 0; $i < $order_item['quantity']; $i++) {
        $items[] = $item;
      }
    }

    $payload = [
      "Details" => $details,
      "PickUpAddress" => $pickup_address,
      "DeliveryAddress" => $destination_address,
      "ItemsInfo" => [
        // Defaulting to Lbs/Inches per client request
        "UOM_W" => "L",
        "UOM_L" => "I",
        "Items" => $items,
      ],
    ];

    // Call ShipTrack API.
    $request = $this->callShipmentApi($payload, $order_uuid);
    $label_id = '';
    $status = 'error';

    if ($request) {
      if ($request->Results->Success === TRUE) {
        $this->logger->get('mbw_shiptrack')->info('Shipment created for order: %order_uuid', ['%order_uuid' => $order_uuid]);

        $status = 'success';
        $label_id = $request->ID;
      } else {
        $this->logger->get('mbw_shiptrack')->error('Shipment could not be created for order: %order_uuid', ['%order_uuid' => $order_uuid]);
        $this->logger->get('mbw_shiptrack')->error($request->Results->Errors[0]->Message);

      }
    } else {
      $this->logger->get('mbw_shiptrack')->error('Shipment could not be created for order: %order_uuid', ['%order_uuid' => $order_uuid]);
    }

    return [
      'status' => $status,
      'label_id' => $label_id,
    ];
  }

  private function callShipmentApi($payload = [], $order_uuid = '')
  {
    $this->logger->get('mbw_shiptrack')->info('Calling ShipTrack shipment API for order: %order_uuid', ['%order_uuid' => $order_uuid]);

    // Build client and call ShipTrack API.
    try {
      $request = $this->http_client->post($this->shiptrack_config->get('endpoint') . '/Jobs/Create', [
        'auth' => [ $this->shiptrack_config->get('username'), $this->shiptrack_config->get('password') ],
        'TranDateTime' => $this->current_date,
        'json' => $payload,
      ]);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      $exception = $e->getMessage();

      $this->logger->get('mbw_shiptrack')->info('ShipTrack shipment API HTTP exception for order %order_uuid: %exception', [
        '%order_uuid' => $order_uuid,
        '%exception' => $exception,
      ]);

      // Return false if we get an exception.
      return FALSE;
    }

    return json_decode($request->getBody());
  }

  public function callLabelApi($order_uuid = '', $label_id = 0)
  {
    $this->logger->get('mbw_shiptrack')->info('Calling ShipTrack label API for order: %order_uuid', ['%order_uuid' => $order_uuid]);

    // Build client and call ShipTrack API.
    try {
      $request = $this->http_client->get($this->shiptrack_config->get('endpoint') . '/Label/GetLabel/' . $label_id, [
        'auth' => [ $this->shiptrack_config->get('username'), $this->shiptrack_config->get('password') ],
        'TranDateTime' => $this->current_date,
      ]);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      $exception = $e->getMessage();

      $this->logger->get('mbw_shiptrack')->info('ShipTrack label API HTTP exception for order %order_uuid: %exception', [
        '%order_uuid' => $order_uuid,
        '%exception' => $exception,
      ]);

      // Return false if we get an exception.
      return FALSE;
    }

    // Create new file and write label to it.
    $file = File::create([
      'uid' => 1,
      'filename' => $order_uuid . '.pdf',
      'uri' => 'private://labels/' . $order_uuid . '.pdf',
      'status' => 1,
    ]);
    $file->save();

    $dir = dirname($file->getFileUri());
    if (!file_exists($dir)) {
      mkdir($dir, 0770, TRUE);
    }
    file_put_contents($file->getFileUri(), $request->getBody());
    $this->logger->get('mbw_shiptrack')->info('ShipTrack label body %order_uuid: %exception %body', [
      '%order_uuid' => $order_uuid,
      '%exception' => $exception,
      '%body' => json_encode($request->getBody()),
    ]);
    $file->save();

    // Return file to controller.
    return $file;
  }
}
