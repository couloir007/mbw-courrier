<?php

namespace Drupal\mbw_order;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an order entity type.
 */
interface OrderInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
