<?php

namespace Drupal\mbw_address;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an address entity type.
 */
interface AddressInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
