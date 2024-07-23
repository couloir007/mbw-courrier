<?php

namespace Drupal\mbw_shipping\Plugin\Block;

use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a user messages block.
 *
 * @Block(
 *   id = "mbw_shipping_user_messages",
 *   admin_label = @Translation("User Messages"),
 *   category = @Translation("MBW")
 * )
 */
class UserMessagesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $messages = [];

    $user = \Drupal::currentUser();
    $user_id = $user->id();

    $query = \Drupal::entityQuery('node')
                      ->condition('type', 'message');
    $orGroup1 = $query->orConditionGroup()
                      ->notExists('field_account')
                      ->condition('field_account', $user_id);
    $query->condition($orGroup1);
    $results = $query->execute();

    if (count($results)) {
      foreach ($results as $message_id) {
        $message = \Drupal::entityTypeManager()
                          ->getStorage('node')
                          ->load($message_id);

        $message_title = $message->label();
        $message_body = $message->get('body')->value;

        $messages[] = [
          'title' => $message_title,
          'body' => $message_body,
        ];
      }
    }

    return [
      '#theme' => 'order_messages',
      '#messages' => $messages,
    ];
  }

  /**
   * @return int
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

}
