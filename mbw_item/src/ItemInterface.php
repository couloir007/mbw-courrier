<?php

namespace Drupal\mbw_item;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an item entity type.
 */
interface ItemInterface extends ContentEntityInterface, EntityOwnerInterface {

}
