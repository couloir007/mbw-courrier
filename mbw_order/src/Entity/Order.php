<?php

namespace Drupal\mbw_order\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Annotation\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mbw_order\OrderInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\Entity\User;

/**
 * Defines the order entity class.
 *
 * @ContentEntityType(
 *   id = "order",
 *   label = @Translation("Order"),
 *   label_collection = @Translation("Orders"),
 *   label_singular = @Translation("order"),
 *   label_plural = @Translation("orders"),
 *   label_count = @PluralTranslation(
 *     singular = "@count orders",
 *     plural = "@count orders",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mbw_order\OrderListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\mbw_order\OrderAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\mbw_order\Form\OrderForm",
 *       "edit" = "Drupal\mbw_order\Form\OrderForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "order",
 *   admin_permission = "administer order",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/order",
 *     "add-form" = "/order/add",
 *     "canonical" = "/order/{order}",
 *     "edit-form" = "/order/{order}/edit",
 *     "delete-form" = "/order/{order}/delete",
 *   },
 *   field_ui_base_route = "entity.order.settings",
 * )
 */
class Order extends ContentEntityBase implements OrderInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 1,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the order was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 2,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the order was last edited.'));


    /**
     * Custom fields.
     */
    $fields['requested_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Pick-up Request Date'))
      ->setRequired(TRUE)
      ->setSettings([
        'datetime_type' => DateTimeItem::DATETIME_TYPE_DATE,
      ])
      ->setDefaultValue([
        'default_date_type' => 'now',
        'default_date'      => 'now',
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ]);

    $fields['field_shipping_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Shipping Type'))
      ->setRequired(TRUE)
      ->setDefaultValue('prepaid')
      ->setSettings([
        'allowed_values' => [
          'prepaid' => 'Prepaid',
          'collect' => 'Collect',
          'third_party' => 'Third Party',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 7,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 7,
      ]);

    $fields['field_account_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Account Number'))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 8,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string',
        'weight' => 8,
      ]);

    $fields['field_items'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Items'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'item')
      ->setRequired(TRUE)
      ->setDescription('Maximum combined weight is 2200lbs.')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => 9,
      ])
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'weight' => 9,
      ]);

    $fields['field_pickup_address'] = BaseFieldDefinition::create('address')
      ->setLabel(t('Pickup Address'))
      ->setDefaultValueCallback('Drupal\mbw_order\Entity\Order::setDefaultUserAddress')
      ->setSettings([
        'available_countries' => [
          'CA',
        ],
        'field_overrides' => [
          'organization' => [
            'override' => 'required',
          ],
          'givenName' => [
            'override' => 'hidden',
          ],
          'familyName' => [
            'override' => 'hidden',
          ],
        ]
      ])
      ->setDisplayOptions('form', [
        'type' => 'address',
        'weight' => 11,
      ]);

    $fields['field_destination_address'] = BaseFieldDefinition::create('address')
     ->setLabel(t('Destination Address'))
     ->setDefaultValue([
       'country_code' => 'CA'
     ])
     ->setSettings([
       'available_countries' => [
         'CA',
       ],
       'field_overrides' => [
         'organization' => [
           'override' => 'required',
         ],
         'givenName' => [
           'override' => 'hidden',
         ],
         'familyName' => [
           'override' => 'hidden',
         ],
       ]
     ])
     ->setDisplayOptions('form', [
       'type' => 'address',
       'weight' => 13,
     ]);

    $fields['field_save_address_pickup'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Save Address'));
    $fields['field_save_address_destination'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Save Address'));

    $fields['field_destination_phone'] = BaseFieldDefinition::create('telephone')
      ->setLabel(t('Phone Number'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'phone',
        'weight' => 15,
      ]);

    $fields['field_destination_email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email Address'))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'phone',
        'weight' => 15,
      ]);

    /**
     * User fields.
     */
    $fields['field_user_email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email Address'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 25,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string',
        'weight' => 25,
      ]);

    $fields['field_user_phone'] = BaseFieldDefinition::create('telephone')
      ->setLabel(t('Phone Number'))
      ->setRequired(TRUE)
      ->setDefaultValueCallback('Drupal\mbw_order\Entity\Order::setDefaultUserPhone')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ])
      ->setDisplayOptions('form', [
        'type' => 'phone',
        'weight' => 20,
      ]);

    $fields['field_comments'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Comments'))
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'basic_string',
        'weight' => 100,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 100,
        'settings' => ['rows' => 4],
      ]);

    /**
     * Hidden/helper fields.
     */
    $fields['field_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('Order status.'))
      ->setRequired(TRUE)
      ->setDefaultValue('order_created')
      ->setSettings([
        'allowed_values' => [
          'order_created' => 'Order Created',
          'pending_payment' => 'Pending Payment',
          'collect_billing' => 'Collect Billing',
          'preauth_success' => 'Pre-auth Success',
          'preauth_failed' => 'Pre-auth Failed',
          'shipping_label_success' => 'Shipping Label Created',
          'shipping_label_failed' => 'Shipping Label Failed',
          'capture_success' => 'Order Complete',
          'capture_failed' => 'Payment Failed',
          'refund_success' => 'Order Refunded',
        ]
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 6,
      ]);

    /**
     * Cost/value fields.
     */
    $fields['field_cost_subtotal'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cost'))
      ->setDescription(t('Final cost for order excluding taxes.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_cost_tax'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tax'))
      ->setDescription(t('Tax to be added to cost.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_fuel_surcharge'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Fuel Surcharge'))
      ->setDescription(t('Fuel surcharge to be added to cost.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_cost_total'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Total Cost'))
      ->setDescription(t('Final cost for order including taxes.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    /**
     * Payment fields.
     */
    $fields['field_payment_ticket_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment Ticket ID'))
      ->setDescription(t('Preload reference returned from payment provider.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_payment_order_no'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment Order Number'))
      ->setDescription(t('Order number returned from payment provider.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_payment_transaction_no'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment Transaction Number'))
      ->setDescription(t('Transaction number returned from payment provider.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    /**
     * ShipTrack fields.
     */
    $fields['field_shiptrack_label_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ShipTrack Label ID'))
      ->setDescription(t('Shipping label number from ShipTrack API.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    return $fields;
  }

  static public function setDefaultUserAddress() {
    $user_id = \Drupal::currentUser()->id();
    $user = User::load($user_id);

    $country_code = 'CA';

    if ($user_id && $user) {
      $address = $user->get('field_address')->getValue()[0];
      $country_code = $address['country_code'];
      $address_line1 = $address['address_line1'];
      $company = $user->get('field_company_name')->value;
      $locality = $address['locality'];
      $administrative_area = $address['administrative_area'];
      $postal_code = $address['postal_code'];
    }

    return [
      'country_code' => $country_code,
      'organization' => $company ?? '',
      'address_line1' => $address_line1 ?? '',
      'locality' => $locality ?? '',
      'administrative_area' => $administrative_area ?? '',
      'postal_code' => $postal_code ?? '',
    ];
  }

  static public function setDefaultUserPhone() {
    $user_id = \Drupal::currentUser()->id();
    $user = User::load($user_id);
    $phone_number = '';

    if ($user_id && $user) {
      $phone_number = $user->get('field_contact_phone')->value;
    }

    return $phone_number;
  }
}
