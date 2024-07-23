<?php

namespace Drupal\mbw_address\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mbw_address\AddressInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the address entity class.
 *
 * @ContentEntityType(
 *   id = "address",
 *   label = @Translation("Address"),
 *   label_collection = @Translation("Addresses"),
 *   label_singular = @Translation("address"),
 *   label_plural = @Translation("addresses"),
 *   label_count = @PluralTranslation(
 *     singular = "@count addresses",
 *     plural = "@count addresses",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mbw_address\AddressListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\mbw_address\AddressAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\mbw_address\Form\AddressForm",
 *       "edit" = "Drupal\mbw_address\Form\AddressForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mbw_address\Routing\AddressHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "address",
 *   admin_permission = "administer address",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   field_ui_base_route = "entity.address.settings",
 * )
 */
class Address extends ContentEntityBase implements AddressInterface {

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
        'weight' => 15,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the address was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the address was last edited.'));

    $fields['field_address_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Address Type'))
      ->setRequired(TRUE)
      ->setDefaultValue('destination')
      ->setSettings([
        'allowed_values' => [
          'destination' => 'Destination',
          'pickup' => 'Pickup',
        ],
      ]);

    $fields['address'] = BaseFieldDefinition::create('address')
      ->setLabel(t('Address'))
      ->setDefaultValue([
        'country_code' => 'CA',
      ])
      ->setSettings([
        'available_countries' => [
          'CA',
        ],
        'field_overrides' => [
          'givenName' => [
            'override' => 'hidden',
          ],
          'familyName' => [
            'override' => 'hidden',
          ],
          'organization' => [
            'override' => 'required',
          ],
        ]
      ])
      ->setDisplayOptions('form', [
        'type' => 'address',
        'weight' => 20,
      ]);

    $fields['field_email'] = BaseFieldDefinition::create('email')
     ->setLabel(t('Email Address'))
     ->setRequired(FALSE)
     ->setDisplayOptions('view', [
       'label' => 'above',
       'type' => 'string',
       'weight' => 25,
     ])
     ->setDisplayOptions('form', [
       'type' => 'string',
       'weight' => 25,
     ]);

    $fields['field_telephone'] = BaseFieldDefinition::create('telephone')
     ->setLabel(t('Phone Number'))
     ->setRequired(FALSE)
     ->setDisplayOptions('view', [
       'label' => 'above',
       'type' => 'string',
       'weight' => 30,
     ])
     ->setDisplayOptions('form', [
       'type' => 'phone',
       'weight' => 30,
     ]);

    return $fields;
  }

}
