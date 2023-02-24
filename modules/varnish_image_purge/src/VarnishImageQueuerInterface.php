<?php

namespace Drupal\varnish_image_purge;

use Drupal\Core\Entity\EntityInterface;

/**
 * Varnish image queuer interface.
 */
interface VarnishImageQueuerInterface {

  /**
   * Invalidates entity images.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return void
   */
  public function invalidate(EntityInterface $entity);

}
