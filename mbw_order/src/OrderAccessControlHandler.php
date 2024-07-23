<?php

namespace Drupal\mbw_order;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the order entity type.
 */
class OrderAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        // If admin, allow access.
        if ($account->hasPermission('administer orders')) {
          return AccessResult::allowed();
        }
        // If owner of Order and not anonymous user, allow access.
        if ($account->id() && $entity->getOwnerId() == $account->id()) {
          return AccessResult::allowed();
        }
        // Deny access.
        return AccessResult::forbidden();

      case 'update':
        return AccessResult::allowedIfHasPermissions(
          $account,
          ['edit order', 'administer order'],
          'OR',
        );

      case 'delete':
        return AccessResult::allowedIfHasPermissions(
          $account,
          ['delete order', 'administer order'],
          'OR',
        );

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions(
      $account,
      ['create order', 'administer order'],
      'OR',
    );
  }

}
