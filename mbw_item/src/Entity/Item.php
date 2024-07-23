<?php

namespace Drupal\mbw_item\Entity;

use Drupal\Core\Entity\Annotation\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mbw_item\ItemInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the item entity class.
 *
 * @ContentEntityType(
 *   id = "item",
 *   label = @Translation("Item"),
 *   label_collection = @Translation("Items"),
 *   label_singular = @Translation("item"),
 *   label_plural = @Translation("items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count items",
 *     plural = "@count items",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mbw_item\ItemListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\mbw_item\ItemAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\mbw_item\Form\ItemForm",
 *       "edit" = "Drupal\mbw_item\Form\ItemForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mbw_item\Routing\ItemHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "item",
 *   admin_permission = "administer item",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   field_ui_base_route = "entity.item.settings",
 * )
 */
class Item extends ContentEntityBase implements ItemInterface {

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
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner');

    $fields['field_description'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Item Description'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 10,
      ]);

    $fields['field_quantity'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Quantity'))
      ->setRequired(TRUE)
      ->setDefaultValue(1)
      ->setSettings([
        'allowed_values' => [
          1 => '1',
          2 => '2',
          3 => '3',
          4 => '4',
          5 => '5',
          6 => '6',
          7 => '7',
          8 => '8',
          9 => '9',
          10 => '10',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'list_default',
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 15,
      ]);

    $fields['field_weight'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Weight (lbs)'))
      ->setRequired(TRUE)
      ->setSettings([
        'precision' => 10,
        'scale' => 2,
        'min' => 0,
        'max' => 500,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 20,
        'settings' => [
          'placeholder' => '0.00',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_length'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Length (in)'))
      ->setRequired(TRUE)
      ->setSettings([
        'precision' => 10,
        'scale' => 1,
        'min' => 0,
        'max' => 72,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 20,
        'settings' => [
          'placeholder' => '0',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_width'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Width (in)'))
      ->setRequired(TRUE)
      ->setSettings([
        'precision' => 10,
        'scale' => 1,
        'min' => 0,
        'max' => 48,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 20,
        'settings' => [
          'placeholder' => '0',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_height'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Height (in)'))
      ->setRequired(TRUE)
      ->setSettings([
        'precision' => 10,
        'scale' => 1,
        'min' => 0,
        'max' => 72,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 20,
        'settings' => [
          'placeholder' => '0',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    $fields['field_cost'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Item Cost'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ]);

    return $fields;
  }

}
