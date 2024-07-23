<?php

namespace Drupal\mbw_order;

use Drupal\Core\Render\Element\RenderCallbackInterface;

class FormPreRenders implements RenderCallbackInterface {

  public static function mbwOrderPrerender($element) {
    $include_states = ['NS', 'NB', 'PE'];
    $administrative_options = $element['administrative_area']['#options'] ? $element['administrative_area']['#options'] : [];
    $options = array_intersect_key($administrative_options, array_flip($include_states));
    $element['administrative_area']['#options'] = $options;

    return $element;
  }

}