<?php

namespace Drupal\varnish_image_purge\Plugin\Purge\Queuer;

use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuerInterface;

/**
 * Queues every tag that Drupal invalidates internally.
 *
 * @PurgeQueuer(
 *   id = "varnish_image",
 *   label = @Translation("Varnish image queuer"),
 *   description = @Translation("Queues images for invalidation."),
 *   enable_by_default = true,
 *   configform = "\Drupal\varnish_image_purge\Form\VarnishImageQueuerConfigurationForm",
 * )
 */
class VarnishImageQueuer extends QueuerBase implements QueuerInterface {

}
